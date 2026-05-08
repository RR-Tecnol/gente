<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EsocialXmlService
{
    /**
     * Helper interno para gerar o ID único do evento no eSocial
     * Formato: ID + tipoInscricao(1) + CNPJ/CPF(14) + dataHora(14) + sequencial(5) = 36 chars
     */
    private function gerarIdEvento(string $tipoInscricao, string $cnpj, int $seq): string
    {
        return 'ID' . $tipoInscricao . str_pad($cnpj, 14, '0', STR_PAD_LEFT) . now()->format('YmdHis') . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Helper para buscar dados básicos do funcionário.
     *
     * Schema real (auditado por Claude via MCP):
     *   - PESSOA tem PESSOA_DATA_NASCIMENTO (não PESSOA_NASCIMENTO)
     *   - PESSOA tem PESSOA_GENERO/PESSOA_RACA/PESSOA_ESTADO_CIVIL/PESSOA_ESCOLARIDADE (integers → mapear)
     *   - PESSOA tem PESSOA_ENDERECO/PESSOA_CEP/BAIRRO_ID/CIDADE_ID
     *   - PESSOA NÃO TEM PIS_PASEP no schema atual (Schema::hasColumn check defensivo)
     */
    private function getFuncionarioDados(int $funcionarioId)
    {
        $temPisPasep = \Illuminate\Support\Facades\Schema::hasColumn('PESSOA', 'PIS_PASEP');

        $cols = [
            'f.*',
            'p.PESSOA_NOME',
            'p.PESSOA_CPF_NUMERO',
            'p.PESSOA_DATA_NASCIMENTO',
            'p.PESSOA_GENERO',
            'p.PESSOA_RACA',
            'p.PESSOA_ESTADO_CIVIL',
            'p.PESSOA_ESCOLARIDADE',
            'p.PESSOA_ENDERECO',
            'p.PESSOA_CEP',
            'p.BAIRRO_ID',
            'p.CIDADE_ID',
            'c.CARGO_NOME',
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
            $cols[] = 'c.CBO';
        }
        if ($temPisPasep) {
            $cols[] = 'p.PIS_PASEP';
        }

        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select($cols)
            ->first();

        if (!$func) {
            throw new \Exception("Funcionário $funcionarioId não encontrado.");
        }

        // Compat: se PIS_PASEP não existe, garantir property null (evitar undefined property warning)
        if (!$temPisPasep) {
            $func->PIS_PASEP = null;
        }

        return $func;
    }

    /**
     * Helper para buscar bairro, cidade e UF de uma PESSOA — necessário em S-2200 (endereço).
     * Schema-tolerante: se BAIRRO/CIDADE/UF não existirem, retorna defaults.
     *
     * @return array{bairro: string, cidade: string, uf: string, cod_munic: ?string}
     */
    private function getEnderecoExpandido($func): array
    {
        $bairro = 'Centro';
        $cidade = 'São Luís';
        $uf = 'MA';
        $codMunic = '2111300'; // IBGE São Luís default

        if ($func->BAIRRO_ID && \Illuminate\Support\Facades\Schema::hasTable('BAIRRO')) {
            $b = DB::table('BAIRRO')->where('BAIRRO_ID', $func->BAIRRO_ID)->first();
            if ($b && isset($b->BAIRRO_NOME)) {
                $bairro = (string) $b->BAIRRO_NOME;
            }
        }

        if ($func->CIDADE_ID && \Illuminate\Support\Facades\Schema::hasTable('CIDADE')) {
            $c = DB::table('CIDADE')
                ->leftJoin('UF', 'UF.UF_ID', '=', 'CIDADE.UF_ID')
                ->where('CIDADE.CIDADE_ID', $func->CIDADE_ID)
                ->select('CIDADE.CIDADE_NOME', 'UF.UF_SIGLA', 'CIDADE.CIDADE_CODIGO_IBGE')
                ->first();
            if ($c) {
                $cidade = (string) ($c->CIDADE_NOME ?? $cidade);
                $uf = (string) ($c->UF_SIGLA ?? $uf);
                $codMunic = $c->CIDADE_CODIGO_IBGE ? (string) $c->CIDADE_CODIGO_IBGE : $codMunic;
            }
        }

        return ['bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'cod_munic' => $codMunic];
    }

    /**
     * Mapeia integer de domínio (PESSOA_GENERO/RACA/EST_CIVIL/ESCOLARIDADE) para código eSocial.
     */
    private function mapearDominio(string $tipo, $valor, string $default): string
    {
        if ($valor === null) {
            return $default;
        }
        $map = config('esocial.mapeamento.' . $tipo, []);
        $key = (int) $valor;

        return (string) ($map[$key] ?? $default);
    }

    /**
     * S-1200 — Remuneração de Trabalhador.
     *
     * Correções Fase 5:
     *   - R52: query corrigida para JOIN DETALHE_FOLHA + FOLHA por FUNCIONARIO_ID + competência,
     *          campo correto DETALHE_FOLHA_PROVENTOS (não FOLHA_BRUTO).
     *   - R53: tpAmb via config('esocial.ambiente') (default 2=homologação).
     *   - R55: perApur normalizado para AAAA-MM via Carbon (não recebe AAAA-MM-DD).
     *   - R59: CNPJ via config('esocial.cnpj_empregador').
     *   - R60: indRetif via config('esocial.ind_retif_default'), permitindo retificação.
     *
     * @param  int     $funcionarioId
     * @param  string  $competencia      AAAA-MM, AAAAMM ou AAAA-MM-DD (será normalizado)
     * @param  int     $indRetif         1=original, 2=retificação, 3=exclusão
     * @param  string  $codCateg         Código de categoria do trabalhador no eSocial (default 301)
     */
    public function gerarS1200(int $funcionarioId, string $competencia, int $indRetif = 0, string $codCateg = '301'): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);

        // R55: normalizar competência para AAAA-MM (qualquer entrada → formato fixo)
        $perApur = $this->normalizarCompetenciaYm($competencia);

        // R52: query correta — DETALHE_FOLHA tem FUNCIONARIO_ID; FOLHA tem FOLHA_COMPETENCIA.
        // Soma DETALHE_FOLHA_PROVENTOS (não FOLHA_BRUTO, que não existe).
        // Schema FOLHA_COMPETENCIA é string sem hífen (AAAAMM); convertemos.
        $compSemHifen = str_replace('-', '', $perApur);
        $remuneracaoTotal = (float) DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('df.FUNCIONARIO_ID', $funcionarioId)
            ->where('f.FOLHA_COMPETENCIA', $compSemHifen)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->sum('df.DETALHE_FOLHA_PROVENTOS');

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');

        // R53: ambiente via config (default 2=homologação)
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config (default 1=original)
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $valorFmt = number_format($remuneracaoTotal, 2, '.', '');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v02_01_00">
  <evtRemun Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <perApur>{$perApur}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$matricula}</matricula>
    </ideVinculo>
    <dmDev>
      <codCateg>{$codCateg}</codCateg>
      <infoPerApur>
        <ideEstabLot>
          <remunPerApur>
            <vrTotCont>{$valorFmt}</vrTotCont>
          </remunPerApur>
        </ideEstabLot>
      </infoPerApur>
    </dmDev>
  </evtRemun>
</eSocial>
XML;

        return $xml;
    }

    /**
     * Normaliza competência para AAAA-MM independente do input (R55).
     * Aceita: 'AAAA-MM', 'AAAAMM', 'AAAA-MM-DD', timestamp.
     */
    private function normalizarCompetenciaYm(string $entrada): string
    {
        $limpa = preg_replace('/\D/', '', $entrada);

        // Se tem 6+ dígitos, primeiros 4 são ano e próximos 2 são mês
        if (strlen($limpa) >= 6) {
            return substr($limpa, 0, 4) . '-' . substr($limpa, 4, 2);
        }

        // Fallback: tentar parsear via Carbon
        try {
            return \Carbon\Carbon::parse($entrada)->format('Y-m');
        } catch (\Throwable $e) {
            return now()->format('Y-m');
        }
    }

    /**
     * S-2200 - Cadastramento Inicial do Vínculo e Admissão/Ingresso de Trabalhador
     */
    public function gerarS2200(int $funcionarioId): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $pisLimpo = preg_replace('/\D/', '', $func->PIS_PASEP ?? '');
        
        // Minimalistic valid structure for S-2200
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v02_01_00">
  <evtAdmissao Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$func->FUNCIONARIO_DATA_INICIO}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <trabalhador>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <nmTrab>{$func->PESSOA_NOME}</nmTrab>
      <sexo>M</sexo>
      <racaCor>1</racaCor>
      <estCiv>1</estCiv>
      <grauInstr>01</grauInstr>
      <dataNascimento>{$func->PESSOA_NASCIMENTO}</dataNascimento>
      <endereco>
        <brasil>
          <tpLograd>Rua</tpLograd>
          <dscLograd>Nao Informado</dscLograd>
          <nrLograd>S/N</nrLograd>
          <bairro>Centro</bairro>
          <cep>65000000</cep>
          <codMunic>2111300</codMunic>
          <uf>MA</uf>
        </brasil>
      </endereco>
      <documentos>
        <NIS>{$pisLimpo}</NIS>
      </documentos>
    </trabalhador>
    <vinculo>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
      <tpRegTrab>2</tpRegTrab>
      <tpRegPrev>2</tpRegPrev>
      <cadIni>S</cadIni>
      <infoContrato>
        <codCateg>301</codCateg>
        <remuneracao>
          <vrSalFx>1412.00</vrSalFx>
          <undSalFixo>5</undSalFixo>
        </remuneracao>
        <duracao>
          <tpContr>1</tpContr>
        </duracao>
        <localTrabalho>
          <localTrabGeral>
            <tpInsc>1</tpInsc>
            <nrInsc>{$cnpj}</nrInsc>
          </localTrabGeral>
        </localTrabalho>
      </infoContrato>
    </vinculo>
  </evtAdmissao>
</eSocial>
XML;
        return $xml;
    }

    /**
     * S-2206 - Alteração de Contrato de Trabalho/Vínculo
     */
    public function gerarS2206(int $funcionarioId): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAltContratual/v02_01_00">
  <evtAltContratual Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$func->FUNCIONARIO_DATA_INICIO}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
    </ideVinculo>
    <altContratual>
      <dtAlteracao>{$func->FUNCIONARIO_DATA_INICIO}</dtAlteracao>
      <infoCargo>
        <codCargo>{$func->CARGO_ID}</codCargo>
      </infoCargo>
    </altContratual>
  </evtAltContratual>
</eSocial>
XML;
        return $xml;
    }

    /**
     * S-2299 - Desligamento
     */
    public function gerarS2299(int $funcionarioId, string $dataDesligamento = null): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        $dtDesligamento = $dataDesligamento ?? $func->FUNCIONARIO_DATA_FIM ?? now()->format('Y-m-d');
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtDeslig/v02_01_00">
  <evtDeslig Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$dtDesligamento}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
    </ideVinculo>
    <infoDeslig>
      <mtvDeslig>02</mtvDeslig>
      <dtDeslig>{$dtDesligamento}</dtDeslig>
      <indPagtoAPI>N</indPagtoAPI>
    </infoDeslig>
  </evtDeslig>
</eSocial>
XML;
        return $xml;
    }
}
