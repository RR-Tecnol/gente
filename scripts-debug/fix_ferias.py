import re

file_path = "c:\\Users\\joaob\\Desktop\\sisgep-job-main\\resources\\gente-v3\\src\\views\\rh\\FeriasLicencasView.vue"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Emojis (Template)
content = content.replace("??? Recursos Humanos", "👥 Recursos Humanos")
content = content.replace("??? Frias", "🏖️ Férias")
content = content.replace("?? Afastamentos", "📋 Afastamentos")
content = content.replace("?? Excede", "⚠️ Excede")
content = content.replace("<span class=\"overlap-ico\">??</span>", "<span class=\"overlap-ico\">⚠️</span>")
content = content.replace("<span class=\"uz-ico\">??</span>", "<span class=\"uz-ico\">📎</span>")
content = content.replace("<span class=\"uz-ico\">?</span>", "<span class=\"uz-ico\">✅</span>")
content = content.replace("? {{ editandoId ? 'Salvar Alteraes' : 'Confirmar Agendamento' }}", "📤 {{ editandoId ? 'Salvar Alterações' : 'Confirmar Agendamento' }}")
content = content.replace("?? Editar", "✏️ Editar")
content = content.replace("? Cancelar", "❌ Cancelar")
content = content.replace("<span class=\"pa-res-ico\">??</span>", "<span class=\"pa-res-ico\">📊</span>")
content = content.replace("?? Vencido", "⚠️ Vencido")
content = content.replace("? Gozado", "✅ Gozado")
content = content.replace("?? Disponvel", "✅ Disponível")
content = content.replace("? {{ p.usados_dias }} dias usados", "✅ {{ p.usados_dias }} dias usados")
content = content.replace("?? {{ p.saldo_dias }} dias restantes", "📅 {{ p.saldo_dias }} dias restantes")
content = content.replace("?? Enviar Solicitao", "📤 Enviar Solicitação")

# Textos com acentos no Template e arrays estáticos
content = content.replace("Frias e Afastamentos", "Férias e Afastamentos")
content = content.replace("frias, licenas e afastamentos", "férias, licenças e afastamentos")
content = content.replace("Frias Disponveis", "Férias Disponíveis")
content = content.replace("Prx. venc.", "Próx. venc.")
content = content.replace("Afastamentos / Licenas", "Afastamentos / Licenças")
content = content.replace("MDULO: FRIAS", "MÓDULO: FÉRIAS")
content = content.replace("Agendar Frias", "Agendar Férias")
content = content.replace("Data de Incio", "Data de Início")
content = content.replace("disponvel", "disponível")
content = content.replace("sobreposio", "sobreposição")
content = content.replace("tambm", "também")
content = content.replace("estaro", "estarão")
content = content.replace("perodo", "período")
content = content.replace("Perodo", "Período")
content = content.replace("Incio", "Início")
content = content.replace("edio", "edição")
content = content.replace("Alteraes", "Alterações")
content = content.replace("HISTRICO FRIAS", "HISTÓRICO FÉRIAS")
content = content.replace("CALENDRIO", "CALENDÁRIO")
content = content.replace("PERODOS AQUISITIVOS", "PERÍODOS AQUISITIVOS")
content = content.replace("Perodos", "Períodos")
content = content.replace("Disponvel", "Disponível")
content = content.replace("concludo", "concluído")
content = content.replace("So necessrios", "São necessários")
content = content.replace("servio", "serviço")
content = content.replace("MDULO: AFASTAMENTOS / LICENAS", "MÓDULO: AFASTAMENTOS / LICENÇAS")
content = content.replace("Licena", "Licença")
content = content.replace("Solicitao", "Solicitação")
content = content.replace("informaes", "informações")
content = content.replace("Comprobatrio", "Comprobatório")
content = content.replace("HISTRICO AFASTAMENTOS", "HISTÓRICO AFASTAMENTOS")
content = content.replace("Histrico", "Histórico")
content = content.replace("Calendrio", "Calendário")

# Arrays in script
content = content.replace("ico: '??', nome: editandoId.value ? 'Editar' : 'Agendar'", "ico: '📅', nome: editandoId.value ? 'Editar' : 'Agendar'")
content = content.replace("ico: '??', nome: 'Histórico', count:", "ico: '📋', nome: 'Histórico', count:")
content = content.replace("ico: '??',  nome: 'Calendário'", "ico: '🗓️',  nome: 'Calendário'")
content = content.replace("ico: '??', nome: 'Períodos", "ico: '📊', nome: 'Períodos")

# L545-588: Tipos de Afastamento
# Premio
content = content.replace("ico: '??', nome: 'Licença Prmio'", "ico: '🏆', nome: 'Licença Prêmio'")
content = content.replace("remunerao", "remuneração")
content = content.replace("aps", "após")
content = content.replace("exerccio", "exercício")
content = content.replace("pecnia", "pecúnia")
content = content.replace("legislao", "legislação")

# Particulares
content = content.replace("ico: '??', nome: 'Afastamento para Fins Particulares'", "ico: '✈️', nome: 'Afastamento para Fins Particulares'")
content = content.replace("vnculo", "vínculo")
content = content.replace("sade", "saúde")
content = content.replace("pblico", "público")

# Maternidade
content = content.replace("ico: '??', nome: 'Licença Maternidade'", "ico: '🤱', nome: 'Licença Maternidade'")
content = content.replace("previdencirio", "previdenciário")
content = content.replace("Durao", "Duração")
content = content.replace("poltica", "política")

# Paternidade
content = content.replace("ico: '?????', nome: 'Licença Paternidade'", "ico: '👨‍🍼', nome: 'Licença Paternidade'")
content = content.replace("Mnimo", "Mínimo")
content = content.replace("Cidad", "Cidadã")

# Capacitacao / Estudo
content = content.replace("ico: '??', nome: 'Licença p/ Capacitao / Estudo'", "ico: '🎓', nome: 'Licença p/ Capacitação / Estudo'")
content = content.replace("varivel", "variável")
content = content.replace("conveno", "convenção")
content = content.replace("ps-graduao", "pós-graduação")
content = content.replace("administrao", "administração")

# Judicial
content = content.replace("ico: '??', nome: 'Afastamento por Deciso Judicial'", "ico: '⚖️', nome: 'Afastamento por Decisão Judicial'")
content = content.replace("determinao", "determinação")
content = content.replace("deciso", "decisão")


with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Done replacing.")
