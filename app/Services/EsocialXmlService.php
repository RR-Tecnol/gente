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
     * S-2200 — Cadastramento Inicial do Vínculo e Admissão.
     *
     * Correções Fase 5:
     *   - R54: dados pessoais lidos de PESSOA com fallback defensivo (sexo/raça/estCiv/grauInstr via mapeamento config).
     *   - R54: endereço lido de PESSOA + JOIN BAIRRO/CIDADE/UF (não "Rua Nao Informado" hardcoded).
     *   - R54: salário lido de TABELA_SALARIAL/CARGO real (não 1412.00 hardcoded).
     *   - R53: tpAmb via config('esocial.ambiente').
     *   - R59: CNPJ via config('esocial.cnpj_empregador').
     *   - R60: indRetif via parâmetro ou config.
     *
     * Observação eSocial: S-2200 não usa `<perApur>`. Removido. Usa `<dtAdm>` dentro de `<infoRegimeTrab>`.
     *
     * @param  int     $funcionarioId
     * @param  int     $indRetif      1=original, 2=retificação, 3=exclusão (default config)
     */
    public function gerarS2200(int $funcionarioId, int $indRetif = 0): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);

        // R53: ambiente via config
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $pisLimpo = preg_replace('/\D/', '', (string) ($func->PIS_PASEP ?? ''));

        // R54: dados pessoais via mapeamento (PESSOA_GENERO/RACA/ESTADO_CIVIL/ESCOLARIDADE → códigos eSocial)
        $sexo = $this->mapearDominio('sexo', $func->PESSOA_GENERO ?? null, 'M');
        $racaCor = $this->mapearDominio('raca_cor', $func->PESSOA_RACA ?? null, '6'); // 6 = Não informado
        $estCiv = $this->mapearDominio('estado_civil', $func->PESSOA_ESTADO_CIVIL ?? null, '1');
        $grauInstr = $this->mapearDominio('grau_instrucao', $func->PESSOA_ESCOLARIDADE ?? null, '01');

        // R54: endereço real via JOIN BAIRRO + CIDADE + UF (com fallback defensivo)
        $endereco = $this->getEnderecoExpandido($func);
        $bairro = htmlspecialchars($endereco['bairro'], ENT_XML1, 'UTF-8');
        $cidadeCodMunic = htmlspecialchars($endereco['cod_munic'] ?? '2111300', ENT_XML1, 'UTF-8');
        $uf = htmlspecialchars($endereco['uf'], ENT_XML1, 'UTF-8');

        $cep = preg_replace('/\D/', '', (string) ($func->PESSOA_CEP ?? ''));
        if (strlen($cep) !== 8) {
            $cep = '65000000'; // fallback final apenas se CEP inválido — log warning
            \Illuminate\Support\Facades\Log::warning('[EsocialXmlService] CEP inválido para funcionário', [
                'funcionario_id' => $funcionarioId,
                'cep_bruto' => $func->PESSOA_CEP ?? null,
            ]);
        }

        $enderecoBruto = trim((string) ($func->PESSOA_ENDERECO ?? ''));
        $tpLograd = 'Rua';
        $dscLograd = 'Não Informado';
        $nrLograd = 'S/N';
        if ($enderecoBruto !== '') {
            // Tentar separar tipo + descrição + número (heurística simples)
            // Ex: "Rua das Flores, 123" → tpLograd="Rua", dscLograd="das Flores", nrLograd="123"
            if (preg_match('/^(Rua|Av\.?|Avenida|Travessa|Praça|Rod\.?|Rodovia|Alameda|Estrada|Ladeira|Beco)\s+(.+?)(?:,\s*(\S+))?$/iu', $enderecoBruto, $m)) {
                $tpLograd = $m[1];
                $dscLograd = trim($m[2]);
                $nrLograd = $m[3] ?? 'S/N';
            } else {
                $dscLograd = $enderecoBruto;
            }
        }
        $tpLograd = htmlspecialchars($tpLograd, ENT_XML1, 'UTF-8');
        $dscLograd = htmlspecialchars(mb_substr($dscLograd, 0, 100), ENT_XML1, 'UTF-8');
        $nrLograd = htmlspecialchars(mb_substr($nrLograd, 0, 10), ENT_XML1, 'UTF-8');

        // R54: salário real via TABELA_SALARIAL (com fallback para CARGO_SALARIO)
        $salario = $this->resolverSalarioFunc($funcionarioId);
        $vrSalFx = number_format($salario, 2, '.', '');

        $dtAdm = $func->FUNCIONARIO_DATA_INICIO ?? now()->format('Y-m-d');
        $nome = htmlspecialchars((string) ($func->PESSOA_NOME ?? ''), ENT_XML1, 'UTF-8');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v02_01_00">
  <evtAdmissao Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <trabalhador>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <nmTrab>{$nome}</nmTrab>
      <sexo>{$sexo}</sexo>
      <racaCor>{$racaCor}</racaCor>
      <estCiv>{$estCiv}</estCiv>
      <grauInstr>{$grauInstr}</grauInstr>
      <dataNascimento>{$func->PESSOA_DATA_NASCIMENTO}</dataNascimento>
      <endereco>
        <brasil>
          <tpLograd>{$tpLograd}</tpLograd>
          <dscLograd>{$dscLograd}</dscLograd>
          <nrLograd>{$nrLograd}</nrLograd>
          <bairro>{$bairro}</bairro>
          <cep>{$cep}</cep>
          <codMunic>{$cidadeCodMunic}</codMunic>
          <uf>{$uf}</uf>
        </brasil>
      </endereco>
      <documentos>
        <NIS>{$pisLimpo}</NIS>
      </documentos>
    </trabalhador>
    <vinculo>
      <matricula>{$matricula}</matricula>
      <tpRegTrab>2</tpRegTrab>
      <tpRegPrev>2</tpRegPrev>
      <cadIni>S</cadIni>
      <infoRegimeTrab>
        <infoCeletista>
          <dtAdm>{$dtAdm}</dtAdm>
        </infoCeletista>
      </infoRegimeTrab>
      <infoContrato>
        <codCateg>301</codCateg>
        <remuneracao>
          <vrSalFx>{$vrSalFx}</vrSalFx>
          <undSalFixo>5</undSalFixo>
        </remuneracao>
        <duracao>
          <tpContr>1</tpContr>
        </duracao>
        <localTrabalho>
          <localTrabGeral>
            <tpInsc>{$tpInsc}</tpInsc>
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
     * Resolve salário base do funcionário com fallback (TABELA_SALARIAL → CARGO_SALARIO → 0).
     */
    private function resolverSalarioFunc(int $funcionarioId): float
    {
        try {
            $row = DB::table('FUNCIONARIO as f')
                ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                    $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                        ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                        ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
                })
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->where('f.FUNCIONARIO_ID', $funcionarioId)
                ->select('ts.TABELA_VENCIMENTO_BASE', 'c.CARGO_SALARIO')
                ->first();

            if ($row) {
                $sal = (float) ($row->TABELA_VENCIMENTO_BASE ?? 0);
                if ($sal > 0) {
                    return $sal;
                }
                return (float) ($row->CARGO_SALARIO ?? 0);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[EsocialXmlService] falha ao resolver salário', [
                'funcionario_id' => $funcionarioId,
                'erro' => $e->getMessage(),
            ]);
        }

        return 0.0;
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
