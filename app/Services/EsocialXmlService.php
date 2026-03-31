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
     * Helper para buscar dados básicos do funcionário
     */
    private function getFuncionarioDados(int $funcionarioId)
    {
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF_NUMERO', 'p.PESSOA_NASCIMENTO', 'p.PIS_PASEP', 'c.CARGO_NOME', 'c.CBO')
            ->first();

        if (!$func) {
            throw new \Exception("Funcionário $funcionarioId não encontrado.");
        }
        return $func;
    }

    /**
     * S-1200 - Remuneração de Trabalhador
     */
    public function gerarS1200(int $funcionarioId, string $competencia): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        // Formata competência de 'Ym' ou 'Y-m' para 'YYYY-MM' (o schema de S-1200 permite YYYY-MM)
        if (strlen($competencia) === 6) {
            $perApur = substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2);
        } else {
            $perApur = date('Y-m', strtotime($competencia));
        }

        // Calcula total de remuneração da competência baseada na FOLHA gerada
        $remuneracaoTotal = DB::table('FOLHA')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where('FOLHA_COMPETENCIA', str_replace('-', '', $perApur))
            ->sum('FOLHA_BRUTO') ?? '0.00';

        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $codCateg = '301'; // Servidor Público Temporário/Estatutário

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v02_01_00">
  <evtRemun Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$perApur}</perApur>
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
    <dmDev>
      <codCateg>{$codCateg}</codCateg>
      <infoPerApur>
        <ideEstabLot>
          <remunPerApur>
            <vrTotCont>{$remuneracaoTotal}</vrTotCont>
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
