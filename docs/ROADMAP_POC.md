# GENTE v3 — ROADMAP POC
**Criado:** 15/03/2026
**Objetivo:** Sistema funcionando em produção para PoC com Prefeitura de São Luís
**Prazo:** 4 semanas
**Foco absoluto:** Folha de pagamento completa + holerite PDF + dados reais

> Este roadmap substitui qualquer outra ordem de prioridade.
> Tudo que não está aqui foi adiado para depois da PoC.

---

## VISÃO DA POC

O que o gestor municipal precisa VER na apresentação:

1. **Login com perfil real** — entrar com CPF de servidor real
2. **Dashboard com números reais** — total da folha, qtd servidores, custo por secretaria
3. **Folha de pagamento calculada** — proventos, descontos, líquido de servidores reais
4. **Holerite em PDF** — contracheque gerado na hora com dados reais
5. **Portal do servidor** — servidor consulta próprio holerite pelo CPF

Isso fecha o contrato. Tudo o mais é para depois.

---

## SEMANA 1 — Base funcionando (Sprint 0 + Infraestrutura)

### Dias 1-2 — Sprint 0 (login)
- TASK-01: Mover auth para fora do isLocal() → routes/web.php
- TASK-02: Corrigir CORS → config/cors.php
- TASK-03: Corrigir .env → APP_URL + SESSION_DOMAIN
- TASK-05: Remover BOM → progressao_funcional.php
- **Critério:** Logar, navegar, fazer logout sem erros

### Dias 3-4 — VPS e deploy inicial
- Contratar VPS (recomendado: DigitalOcean $12/mês ou Hostinger VPS)
- Configurar: Ubuntu 22 + PHP 8.1 + MySQL/PostgreSQL + Nginx
- Deploy do código + migrations
- Configurar domínio ou IP fixo para a PoC

### Dias 5-7 — Importar dados reais
- Mapear estrutura dos dados reais do município
- Criar script de importação para FUNCIONARIO + PESSOA
- Importar dados de folha para FOLHA + DETALHE_FOLHA
- Validar: folha calcula corretamente com dados reais

---

## SEMANA 2 — Folha de pagamento (Sprint 2 focado na PoC)

### Folha de pagamento completa
- Engine de cálculo: proventos + descontos + líquido
- Competência atual calculada com dados reais
- Endpoint: POST /folhas/calcular funcionando
- Endpoint: GET /folhas/{competencia} retornando dados reais

### Holerite PDF — PRIORIDADE MÁXIMA
- Template HTML profissional com identidade visual do município
- Brasão da PMSL no cabeçalho
- Campos: servidor, matrícula, cargo, secretaria, competência
- Tabela: proventos, descontos, líquido
- Download em PDF via DomPDF
- Endpoint: GET /holerite/{id}/pdf funcionando

### Dashboard com dados reais
- Total da folha do mês atual
- Quantidade de servidores ativos
- Custo por secretaria (gráfico)
- Comparativo mês anterior vs atual

---

## SEMANA 3 — Polimento e UX para a PoC

### Experiência do usuário
- Fluxo de login refinado — feedback visual claro
- Loading states em todas as telas
- Tratamento de erros amigável
- Responsivo para tablet (apresentação pode ser em iPad)

### Portal do servidor
- Servidor acessa pelo CPF
- Vê próprios holerites por competência
- Download do PDF do holerite
- Histórico dos últimos 12 meses

### Segurança mínima para produção
- Sprint de segurança (SEC-01 a SEC-05)
- APP_DEBUG=false
- Rotas /dev/* inacessíveis em produção
- HTTPS configurado na VPS

### Testes com dados reais
- Testar com 3 perfis diferentes (admin, RH, servidor)
- Validar cálculo de folha com dados reais
- Gerar 10 holerites e validar valores

---

## SEMANA 4 — PoC

### Dias 1-2 — Estabilização
- Corrigir bugs encontrados nos testes
- Otimizar performance das queries mais lentas
- Backup automático configurado na VPS

### Dias 3-4 — Preparação da apresentação
- Criar usuário demo para a apresentação
- Preparar roteiro de demonstração
- Treinar o fluxo: login → dashboard → folha → holerite PDF

### Dia 5+ — PoC 🎯
- Apresentação para o município
- Sistema rodando em produção com dados reais
- Gestor consegue ver e baixar o próprio holerite na hora

---

## O QUE FICA PARA DEPOIS DA POC

| Módulo | Motivo de adiar |
|--------|----------------|
| Neoconsig | Não é visível na apresentação |
| Consignação margem 10% | Correção técnica, não visual |
| ERP (tesouraria, patrimônio, contabilidade, orçamento) | Escopo expandido — após contrato |
| Escalas médicas | Origem do sistema — após contrato |
| Relatórios TCE-MA | Compliance — após contrato |
| App mobile | Após contrato |

---

## VPS — RECOMENDAÇÃO

Para a PoC, contratar uma dessas opções:

| Opção | Preço | Specs | Link |
|-------|-------|-------|------|
| DigitalOcean Droplet | ~$12/mês | 2GB RAM, 1 vCPU, 50GB SSD | digitalocean.com |
| Hostinger VPS | ~R$30/mês | 2GB RAM, 1 vCPU, 40GB SSD | hostinger.com.br |
| Contabo VPS S | ~€4/mês | 4GB RAM, 2 vCPU, 50GB SSD | contabo.com |

**Stack na VPS:**
```
Ubuntu 22.04 LTS
PHP 8.1 + Composer
MySQL 8 (mais simples que SQL Server para PoC)
Nginx
Certbot (SSL gratuito)
```

---

## MÉTRICAS DE SUCESSO DA POC

O contrato é ganho se o gestor conseguir:
- [ ] Fazer login com CPF real em menos de 3 segundos
- [ ] Ver o total da folha do mês na tela inicial
- [ ] Abrir o holerite de qualquer servidor
- [ ] Baixar o PDF do holerite
- [ ] Confirmar que os valores estão corretos

---

*GENTE v3 | Roadmap PoC | RR TECNOL | 15/03/2026*
*"Fechar o contrato com São Luís. Depois expandir."*
