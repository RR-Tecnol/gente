<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula APLICACAO (árvore do menu) e ACESSO (permissões por perfil).
 * Usa DB::unprepared() com IDENTITY_INSERT para manter contexto na mesma conexão SQL Server.
 */
class MenuSeeder extends Seeder
{
    private const ADM_GESTAO = [1, 2, 7, 13];
    private const ADM_RH = [1, 2, 6, 7, 8, 13, 14, 15];
    private const ADM_COORD = [1, 2, 6, 7, 8, 11, 12, 13, 14, 15];
    private const APENAS_ADM = [1, 2, 13];
    private const TODOS = [1, 2, 3, 6, 7, 8, 10, 11, 12, 13, 14, 15];

    public function run(): void
    {
        // ── APLICACAO ─────────────────────────────────────────────────────────
        foreach ($this->aplicacoes() as $app) {
            DB::table('APLICACAO')->updateOrInsert(
                ['APLICACAO_ID' => $app['id']],
                [
                    'APLICACAO_NOME' => $app['nome'],
                    'APLICACAO_ICONE' => $app['icon'],
                    'APLICACAO_URL' => $app['url'],
                    'APLICACAO_PAI_ID' => $app['pai'],
                    'APLICACAO_ORDEM' => $app['ordem'],
                    'APLICACAO_ATIVA' => 1,
                    'APLICACAO_GESTAO' => 1,
                ]
            );
        }

        // ── ACESSO ────────────────────────────────────────────────────────────
        foreach ($this->acessos() as [$appId, $perfilId]) {
            DB::table('ACESSO')->updateOrInsert(
                ['APLICACAO_ID' => $appId, 'PERFIL_ID' => $perfilId],
                ['ACESSO_ATIVO' => 1]
            );
        }
    }

    private function aplicacoes(): array
    {
        return [
            // GRUPOS (pai = null)
            ['id' => 1, 'nome' => 'Cadastros', 'icon' => 'mdi-account-box-multiple', 'url' => null, 'pai' => null, 'ordem' => 10],
            ['id' => 2, 'nome' => 'Recursos Humanos', 'icon' => 'mdi-human-greeting', 'url' => null, 'pai' => null, 'ordem' => 20],
            ['id' => 3, 'nome' => 'Escala', 'icon' => 'mdi-calendar-clock', 'url' => null, 'pai' => null, 'ordem' => 30],
            ['id' => 4, 'nome' => 'Folha de Pagamento', 'icon' => 'mdi-currency-usd', 'url' => null, 'pai' => null, 'ordem' => 40],
            ['id' => 5, 'nome' => 'Ponto Eletronico', 'icon' => 'mdi-clock-check-outline', 'url' => null, 'pai' => null, 'ordem' => 50],
            ['id' => 6, 'nome' => 'Relatorios', 'icon' => 'mdi-chart-bar', 'url' => null, 'pai' => null, 'ordem' => 60],
            ['id' => 7, 'nome' => 'Administracao', 'icon' => 'mdi-shield-account', 'url' => null, 'pai' => null, 'ordem' => 70],
            ['id' => 8, 'nome' => 'Inicio', 'icon' => 'mdi-home-outline', 'url' => 'home/view', 'pai' => null, 'ordem' => 1],

            // CADASTROS
            ['id' => 101, 'nome' => 'Pessoas', 'icon' => 'mdi-account', 'url' => 'pessoa/view', 'pai' => 1, 'ordem' => 10],
            ['id' => 102, 'nome' => 'Cargos', 'icon' => 'mdi-briefcase', 'url' => 'cargo/view', 'pai' => 1, 'ordem' => 20],
            ['id' => 103, 'nome' => 'Funcoes', 'icon' => 'mdi-badge-account', 'url' => 'funcao/view', 'pai' => 1, 'ordem' => 30],
            ['id' => 104, 'nome' => 'Vinculos', 'icon' => 'mdi-link-variant', 'url' => 'vinculo/view', 'pai' => 1, 'ordem' => 40],
            ['id' => 105, 'nome' => 'Unidades', 'icon' => 'mdi-hospital-building', 'url' => 'unidade/view', 'pai' => 1, 'ordem' => 50],
            ['id' => 106, 'nome' => 'Setores', 'icon' => 'mdi-floor-plan', 'url' => 'setor/view', 'pai' => 1, 'ordem' => 60],
            ['id' => 107, 'nome' => 'Tipos de Documento', 'icon' => 'mdi-file-document', 'url' => 'tipo_documento/view', 'pai' => 1, 'ordem' => 70],
            ['id' => 108, 'nome' => 'Tabela de Impostos', 'icon' => 'mdi-percent', 'url' => 'tabela_imposto/view', 'pai' => 1, 'ordem' => 80],
            ['id' => 109, 'nome' => 'Eventos', 'icon' => 'mdi-lightning-bolt', 'url' => 'evento/view', 'pai' => 1, 'ordem' => 90],
            ['id' => 110, 'nome' => 'Bancos', 'icon' => 'mdi-bank', 'url' => 'banco/view', 'pai' => 1, 'ordem' => 100],
            ['id' => 111, 'nome' => 'Feriados', 'icon' => 'mdi-calendar-star', 'url' => 'feriado/view', 'pai' => 1, 'ordem' => 110],
            ['id' => 112, 'nome' => 'Tabelas Genericas', 'icon' => 'mdi-table', 'url' => 'tabela_generica/view', 'pai' => 1, 'ordem' => 120],

            // RECURSOS HUMANOS
            ['id' => 201, 'nome' => 'Funcionarios', 'icon' => 'mdi-account-hard-hat', 'url' => 'funcionario/view', 'pai' => 2, 'ordem' => 10],
            ['id' => 202, 'nome' => 'Lotacoes', 'icon' => 'mdi-map-marker-account', 'url' => 'lotacao/view', 'pai' => 2, 'ordem' => 20],
            ['id' => 203, 'nome' => 'Ferias', 'icon' => 'mdi-umbrella-beach', 'url' => 'ferias/view', 'pai' => 2, 'ordem' => 30],
            ['id' => 204, 'nome' => 'Afastamentos', 'icon' => 'mdi-account-clock', 'url' => 'afastamento/view', 'pai' => 2, 'ordem' => 40],
            ['id' => 205, 'nome' => 'Abono de Falta', 'icon' => 'mdi-calendar-check', 'url' => 'abono_falta/view', 'pai' => 2, 'ordem' => 50],
            ['id' => 206, 'nome' => 'Atribuicoes', 'icon' => 'mdi-clipboard-list', 'url' => 'atribuicao/view', 'pai' => 2, 'ordem' => 60],
            ['id' => 207, 'nome' => 'Documentos', 'icon' => 'mdi-file-multiple', 'url' => 'documento/view', 'pai' => 2, 'ordem' => 70],
            ['id' => 208, 'nome' => 'Dossie', 'icon' => 'mdi-folder-account', 'url' => 'dossie/view', 'pai' => 2, 'ordem' => 80],
            ['id' => 209, 'nome' => 'Pre-Cadastro', 'icon' => 'mdi-account-plus', 'url' => 'pre_cadastro/view', 'pai' => 2, 'ordem' => 90],
            ['id' => 210, 'nome' => 'Termos', 'icon' => 'mdi-file-sign', 'url' => 'termo/view', 'pai' => 2, 'ordem' => 100],

            // ESCALA
            ['id' => 301, 'nome' => 'Escalas', 'icon' => 'mdi-calendar-month', 'url' => 'escala/view', 'pai' => 3, 'ordem' => 10],
            ['id' => 302, 'nome' => 'Turnos', 'icon' => 'mdi-clock-time-four', 'url' => 'turno/view', 'pai' => 3, 'ordem' => 20],
            ['id' => 303, 'nome' => 'Substituicoes', 'icon' => 'mdi-account-switch', 'url' => 'substituicao_escala/view', 'pai' => 3, 'ordem' => 30],

            // FOLHA
            ['id' => 401, 'nome' => 'Folhas', 'icon' => 'mdi-file-table', 'url' => 'folha/view', 'pai' => 4, 'ordem' => 10],
            ['id' => 402, 'nome' => 'Contra-Cheque', 'icon' => 'mdi-receipt-text', 'url' => 'holerite/view', 'pai' => 4, 'ordem' => 20],
            ['id' => 403, 'nome' => 'Remessa Bancaria', 'icon' => 'mdi-bank-transfer', 'url' => 'remessa/view', 'pai' => 4, 'ordem' => 30],

            // PONTO
            ['id' => 501, 'nome' => 'Registros de Ponto', 'icon' => 'mdi-fingerprint', 'url' => 'ponto/view', 'pai' => 5, 'ordem' => 10],
            ['id' => 502, 'nome' => 'Apuracao', 'icon' => 'mdi-calculator', 'url' => 'ponto/apuracao', 'pai' => 5, 'ordem' => 20],
            ['id' => 503, 'nome' => 'Justificativas', 'icon' => 'mdi-comment-check', 'url' => 'ponto/justificativas', 'pai' => 5, 'ordem' => 30],
            ['id' => 504, 'nome' => 'Terminais', 'icon' => 'mdi-tablet', 'url' => 'ponto/terminais', 'pai' => 5, 'ordem' => 40],

            // RELATORIOS
            ['id' => 601, 'nome' => 'Relatorios', 'icon' => 'mdi-file-chart', 'url' => 'relatorio/view', 'pai' => 6, 'ordem' => 10],

            // ADMINISTRACAO
            ['id' => 701, 'nome' => 'Usuarios', 'icon' => 'mdi-account-cog', 'url' => 'usuario/view', 'pai' => 7, 'ordem' => 10],
            ['id' => 702, 'nome' => 'Perfis e Acessos', 'icon' => 'mdi-shield-key', 'url' => 'perfil/view', 'pai' => 7, 'ordem' => 20],
            ['id' => 703, 'nome' => 'Aplicacoes', 'icon' => 'mdi-apps', 'url' => 'aplicacao/view', 'pai' => 7, 'ordem' => 30],
            ['id' => 704, 'nome' => 'Configuracoes', 'icon' => 'mdi-cog', 'url' => 'configuracoes/', 'pai' => 7, 'ordem' => 40],
            ['id' => 705, 'nome' => 'Scripts SQL', 'icon' => 'mdi-database-cog', 'url' => 'script/view', 'pai' => 7, 'ordem' => 50],
        ];
    }

    private function acessos(): array
    {
        $mapa = [
            8 => self::TODOS,
            1 => self::ADM_RH,
            2 => self::ADM_COORD,
            3 => self::ADM_COORD,
            4 => self::ADM_RH,
            5 => self::TODOS,
            6 => self::ADM_COORD,
            7 => self::APENAS_ADM,
            101 => self::ADM_RH,
            102 => self::ADM_RH,
            103 => self::ADM_RH,
            104 => self::ADM_RH,
            105 => self::ADM_RH,
            106 => self::ADM_RH,
            107 => self::ADM_GESTAO,
            108 => self::ADM_GESTAO,
            109 => self::ADM_RH,
            110 => self::ADM_GESTAO,
            111 => self::ADM_RH,
            112 => self::APENAS_ADM,
            201 => self::ADM_COORD,
            202 => self::ADM_COORD,
            203 => self::ADM_COORD,
            204 => self::ADM_COORD,
            205 => self::ADM_COORD,
            206 => self::ADM_COORD,
            207 => self::ADM_COORD,
            208 => self::ADM_RH,
            209 => self::ADM_RH,
            210 => self::APENAS_ADM,
            301 => array_merge(self::ADM_COORD, [3]),
            302 => self::ADM_GESTAO,
            303 => self::ADM_COORD,
            401 => self::ADM_RH,
            402 => self::TODOS,
            403 => self::ADM_GESTAO,
            501 => self::ADM_COORD,
            502 => self::ADM_COORD,
            503 => self::ADM_COORD,
            504 => self::APENAS_ADM,
            601 => self::ADM_COORD,
            701 => self::APENAS_ADM,
            702 => self::APENAS_ADM,
            703 => self::APENAS_ADM,
            704 => self::APENAS_ADM,
            705 => [1],
        ];

        $acessos = [];
        foreach ($mapa as $appId => $perfis) {
            foreach (array_unique($perfis) as $perfilId) {
                $acessos[] = [(int) $appId, (int) $perfilId];
            }
        }
        return $acessos;
    }
}
