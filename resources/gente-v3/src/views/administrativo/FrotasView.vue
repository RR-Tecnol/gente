<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-slate-200">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800">Gestão de Frotas</h1>
        <p class="text-slate-500 mt-1">Controle de veículos, saídas, manutenções e alertas</p>
      </div>
    </div>

    <!-- Error/Sucesso globais -->
    <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
      <div class="flex items-center">
        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
        <p class="text-sm text-red-700">{{ errorMsg }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="border-b border-slate-200">
        <nav class="flex -mb-px px-6 overflow-x-auto" aria-label="Tabs">
          <button
            v-for="tab in tabs" :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              activeTab === tab.id
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
              'whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors duration-200'
            ]"
          >
            <i :data-lucide="tab.icon" class="w-4 h-4"></i>
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <div class="p-6">
        <!-- Loader general -->
        <div v-if="isLoading" class="flex justify-center py-12">
          <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- FROTA (Veículos) -->
        <div v-else-if="activeTab === 'frota'" class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div class="flex gap-4">
              <select v-model="filtersFrota.status" @change="fetchFrota" class="input w-48">
                <option value="">Todos os Status</option>
                <option value="DISPONIVEL">Disponível</option>
                <option value="EM_USO">Em Uso</option>
                <option value="EM_MANUTENCAO">Em Manutenção</option>
                <option value="INATIVO">Inativo</option>
              </select>
              <select v-model="filtersFrota.tipo" @change="fetchFrota" class="input w-48">
                <option value="">Todos os Tipos</option>
                <option value="CARRO">Carro</option>
                <option value="VAN">Van</option>
                <option value="ONIBUS">Ônibus</option>
                <option value="CAMINHAO">Caminhão</option>
                <option value="MOTO">Moto</option>
                <option value="AMBULANCIA">Ambulância</option>
              </select>
            </div>
            <button @click="abrirCadastroVeiculo" class="btn btn-primary flex items-center gap-2">
              <i data-lucide="plus" class="w-4 h-4"></i> Cadastrar Veículo
            </button>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Placa</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Marca / Modelo</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Ano</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Tipo</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">KM Atual</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-slate-200">
                <tr v-for="v in veiculos" :key="v.VEICULO_ID" class="hover:bg-slate-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-900 border-l-4" 
                      :class="statusColors[v.VEICULO_STATUS]?.border || 'border-slate-200'">{{ v.VEICULO_PLACA }}</td>
                  <td class="px-6 py-4 text-sm text-slate-500">{{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500">{{ v.VEICULO_ANO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500">{{ v.VEICULO_TIPO }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-slate-700">{{ v.VEICULO_KM_ATUAL }} km</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span :class="statusColors[v.VEICULO_STATUS]?.badge" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ v.VEICULO_STATUS }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!veiculos.length">
                  <td colspan="6" class="px-6 py-8 text-center text-slate-500">Nenhum veículo encontrado com os filtros informados.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SAÍDAS -->
        <div v-else-if="activeTab === 'saidas'" class="space-y-8">
          <!-- Nova Saída Form (Inline) -->
          <div class="bg-slate-50 border border-slate-200 p-6 rounded-lg shadow-inner">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
              <i data-lucide="log-out" class="w-5 h-5 text-indigo-500"></i> Registrar Nova Saída
            </h3>
            <form @submit.prevent="registrarSaida" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div class="lg:col-span-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Veículo (Apenas Disponíveis)</label>
                <select v-model="formSaida.veiculo_id" required class="input w-full" @change="onVeiculoSaidaChange">
                  <option value="">Selecione...</option>
                  <option v-for="v in veiculosDisponiveis" :key="v.VEICULO_ID" :value="v.VEICULO_ID">
                    {{ v.VEICULO_PLACA }} - {{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }} ({{ v.VEICULO_KM_ATUAL }} km)
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ID Motorista (Funcionário)</label>
                <!-- O Certo em produção seria um autocomplete. Por simplicidade do SPEC será ID direto. -->
                <input v-model="formSaida.motorista_id" type="number" required placeholder="Ex: 1024" class="input w-full">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Data/Hora de Saída</label>
                <input v-model="formSaida.saida_data_hora" type="datetime-local" required class="input w-full">
              </div>
              <div class="lg:col-span-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">KM de Saída</label>
                <input v-model="formSaida.km_saida" type="number" required class="input w-full" readonly title="KM preenchido automaticamente com base no veículo">
              </div>
              <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Destino</label>
                <input v-model="formSaida.saida_destino" type="text" maxlength="200" required class="input w-full">
              </div>
              <div class="lg:col-span-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Finalidade da Viagem</label>
                <input v-model="formSaida.saida_finalidade" type="text" maxlength="200" required class="input w-full">
              </div>
              <div class="lg:col-span-3 flex justify-end">
                <button type="submit" class="btn btn-primary" :disabled="isSaving">
                  <span v-if="isSaving">Registrando...</span>
                  <span v-else>Confirmar Saída do Veículo</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Saídas em Aberto -->
          <div>
            <h3 class="text-lg font-bold text-slate-800 border-b pb-2 mb-4">Saídas em Aberto (Aguardando Retorno)</h3>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                  <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Data/Hora Saída</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Veículo</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Motorista</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Destino</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">KM Saída</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                  <tr v-for="s in saidasAbertas" :key="s.SAIDA_ID" class="hover:bg-slate-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900 font-medium">{{ formatDateTime(s.SAIDA_DATA_HORA) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500">{{ s.VEICULO_PLACA }} - {{ s.VEICULO_MODELO }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 truncate max-w-[150px]">{{ s.motorista }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 truncate max-w-[150px]" :title="s.SAIDA_DESTINO">{{ s.SAIDA_DESTINO }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-slate-700">{{ s.KM_SAIDA }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                      <button @click="abrirRetornoModal(s)" class="btn btn-outline btn-sm">Reg. Retorno</button>
                    </td>
                  </tr>
                  <tr v-if="!saidasAbertas.length">
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">Nenhum veículo aguardando retorno neste momento.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- MANUTENÇÕES -->
        <div v-else-if="activeTab === 'manutencoes'" class="space-y-6">
          <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar/Filtro -> Veículo ID -->
            <div class="w-full md:w-1/3 space-y-4">
              <label class="block text-sm font-medium text-slate-700">Selecione o Veículo para Histórico/Nova Manutenção</label>
              <select v-model="selectedVeiculoHistorico" @change="fetchHistorico" class="input w-full">
                <option value="">-- Selecione um Veículo --</option>
                <option v-for="v in veiculos" :key="v.VEICULO_ID" :value="v.VEICULO_ID">
                  {{ v.VEICULO_PLACA }} - {{ v.VEICULO_MODELO }} ({{ v.VEICULO_STATUS }})
                </option>
              </select>

              <div v-if="selectedVeiculoHistorico" class="bg-slate-50 p-4 rounded-lg border border-slate-200 mt-4">
                <button @click="abrirManutencaoModal" class="btn btn-primary w-full flex justify-center items-center gap-2">
                  <i data-lucide="wrench" class="w-4 h-4"></i> Registrar Manutenção
                </button>
              </div>
            </div>

            <div class="w-full md:w-2/3 border border-slate-200 rounded-lg p-6" v-if="selectedVeiculoHistorico">
              <h3 class="text-lg font-bold text-slate-800 border-b pb-2 mb-4">Histórico de Manutenções</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                  <thead class="bg-slate-50">
                    <tr>
                      <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                      <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Tipo</th>
                      <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Descrição / Fornecedor</th>
                      <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Valor</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-slate-200">
                    <tr v-for="m in historicoVeiculo.manutencoes" :key="m.MANUT_ID">
                      <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-slate-900">{{ formatDate(m.MANUT_DATA) }}</td>
                      <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                        <span :class="m.MANUT_TIPO === 'PREVENTIVA' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'" class="px-2 py-0.5 rounded text-xs font-semibold">
                          {{ m.MANUT_TIPO }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm text-slate-500">
                        <span class="block font-medium text-slate-700">{{ m.MANUT_DESCRICAO }}</span>
                        <span v-if="m.MANUT_FORNECEDOR" class="text-xs text-slate-400">Fornecedor: {{ m.MANUT_FORNECEDOR }}</span>
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-700">
                        {{ m.MANUT_VALOR ? formatCurrency(m.MANUT_VALOR) : '-' }}
                      </td>
                    </tr>
                    <tr v-if="!historicoVeiculo.manutencoes?.length">
                      <td colspan="4" class="px-4 py-6 text-center text-slate-500">Nenhuma manutenção registrada para este carro.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="w-full md:w-2/3 flex items-center justify-center border border-slate-200 border-dashed rounded-lg p-6 bg-slate-50 min-h-[300px]">
              <p class="text-slate-500 text-center">
                <i data-lucide="car-front" class="w-12 h-12 mx-auto mb-2 text-slate-300"></i>
                Selecione um veículo para visualizar seu histórico e operar manutenções.
              </p>
            </div>
          </div>
        </div>

        <!-- ALERTAS DE MANUTENÇÃO -->
        <div v-else-if="activeTab === 'alertas'" class="space-y-4">
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Veículo</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Data Prevista</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Sinal / Dias</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-slate-200">
                <tr v-for="v in alertasManutencao" :key="v.VEICULO_ID" class="hover:bg-slate-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                    {{ v.VEICULO_PLACA }} - {{ v.VEICULO_MARCA }} {{ v.VEICULO_MODELO }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 font-bold">
                    {{ formatDate(v.VEICULO_PROX_MANUTENCAO) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span :class="v.urgencia === 'CRITICO' ? 'bg-red-100 text-red-800 animate-pulse' : 'bg-yellow-100 text-yellow-800'" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ v.dias_restantes < 0 ? 'Atrasado ' + Math.abs(v.dias_restantes) + ' dias' : (v.dias_restantes === 0 ? 'Hoje!' : 'Faltam ' + v.dias_restantes + ' dias') }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <button @click="abrirManutencaoFromAlerta(v.VEICULO_ID)" class="btn btn-outline btn-sm border-slate-300">
                      Visualizar
                    </button>
                  </td>
                </tr>
                <tr v-if="!alertasManutencao.length">
                  <td colspan="4" class="px-6 py-8 text-center text-slate-500">Nenhum veículo com manutenção prevista para os próximos 30 dias.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>


    <!-- MODAIS DE CADASTRO E OPERAÇÃO -->

    <!-- Novo Veiculo Modal -->
    <div v-if="cadastroVeiculoModal" class="fixed inset-0 z-50 flex py-6 justify-center bg-slate-900/50 backdrop-blur-sm overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl flex flex-col mx-4 h-fit">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Cadastrar Novo Veículo</h3>
          <button @click="cadastroVeiculoModal = false" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <form @submit.prevent="salvarVeiculo" class="p-6 space-y-4">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Placa *</label>
              <input v-model="formVeiculo.veiculo_placa" type="text" maxlength="10" required placeholder="AAA-1234" class="input w-full uppercase">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Marca *</label>
              <input v-model="formVeiculo.veiculo_marca" type="text" maxlength="50" required placeholder="Fiat, VW..." class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Modelo *</label>
              <input v-model="formVeiculo.veiculo_modelo" type="text" maxlength="100" required placeholder="Uno, Gol..." class="input w-full">
            </div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Ano *</label>
              <input v-model="formVeiculo.veiculo_ano" type="number" min="1900" max="2100" required class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tipo *</label>
              <select v-model="formVeiculo.veiculo_tipo" required class="input w-full">
                <option value="CARRO">Carro</option>
                <option value="VAN">Van</option>
                <option value="ONIBUS">Ônibus</option>
                <option value="CAMINHAO">Caminhão</option>
                <option value="MOTO">Moto</option>
                <option value="AMBULANCIA">Ambulância</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Cor</label>
              <input v-model="formVeiculo.veiculo_cor" type="text" maxlength="30" class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">KM Atual</label>
              <input v-model="formVeiculo.veiculo_km_atual" type="number" min="0" required class="input w-full">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">RENAVAM</label>
            <input v-model="formVeiculo.veiculo_renavam" type="text" maxlength="20" class="input w-full">
          </div>
          
          <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 mt-6">
            <button type="button" @click="cadastroVeiculoModal = false" class="btn btn-outline" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="isSaving">Salvar Veículo</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Retorno Saída Modal -->
    <div v-if="retornoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Registrar Retorno</h3>
          <button @click="retornoModal = false" class="text-slate-400">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <form @submit.prevent="salvarRetorno" class="p-6 space-y-4">
          <div class="bg-slate-100 p-3 rounded text-sm text-slate-700 flex flex-col mb-4">
            <span class="font-bold border-b pb-1 mb-1">Dados da Saída</span>
            <span>Motorista: {{ selectedSaida?.motorista }}</span>
            <span>Destino: {{ selectedSaida?.SAIDA_DESTINO }}</span>
            <span>KM Inicial na Saída: <strong class="text-emerald-600">{{ selectedSaida?.KM_SAIDA }} km</strong></span>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data/Hora Retorno</label>
            <input v-model="formRetorno.retorno_data_hora" type="datetime-local" required class="input w-full">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">KM Final do Hodômetro</label>
            <input v-model="formRetorno.km_retorno" type="number" :min="selectedSaida?.KM_SAIDA" required class="input w-full">
            <span class="text-xs text-slate-500 mt-1 block">Obrigatório ser maior ou igual a {{ selectedSaida?.KM_SAIDA }} km</span>
          </div>
          
          <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 mt-6">
            <button type="button" @click="retornoModal = false" class="btn btn-outline" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 border-none" :disabled="isSaving">Confirmar Chegada</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Registro de Manutenção Modal -->
    <div v-if="manutencaoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <h3 class="text-lg font-bold text-slate-800">Nova Manutenção</h3>
          <button @click="manutencaoModal = false" class="text-slate-400">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
        <form @submit.prevent="salvarManutencao" class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de Manutenção</label>
              <select v-model="formManut.manut_tipo" required class="input w-full">
                <option value="PREVENTIVA">Preventiva (Revisão)</option>
                <option value="CORRETIVA">Corretiva (Falha)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Data de Realização</label>
              <input v-model="formManut.manut_data" type="date" required class="input w-full">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descrição do Serviço Efetuado</label>
            <textarea v-model="formManut.manut_descricao" rows="2" maxlength="300" required class="input w-full"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Custo Total (R$)</label>
              <input v-model="formManut.manut_valor" type="number" step="0.01" class="input w-full">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Próxima Manutenção Prev</label>
              <input v-model="formManut.manut_proxima" type="date" class="input w-full">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fornecedor / Oficina</label>
            <input v-model="formManut.manut_fornecedor" type="text" maxlength="150" class="input w-full">
          </div>
          
          <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 mt-6">
            <button type="button" @click="manutencaoModal = false" class="btn btn-outline" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="isSaving">Salvar Manutenção</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import api from '@/plugins/axios'
import lucide from 'lucide'

const tabs = [
  { id: 'frota', name: 'Controle de Veículos', icon: 'car-front' },
  { id: 'saidas', name: 'Saídas e Retornos', icon: 'route' },
  { id: 'manutencoes', name: 'Manutenções', icon: 'wrench' },
  { id: 'alertas', name: 'Alertas', icon: 'bell' }
]

const activeTab = ref('frota')
const isLoading = ref(false)
const isSaving = ref(false)
const errorMsg = ref('')

// Dados Rest APis
const veiculos = ref([])
const veiculosDisponiveis = ref([])
const saidasAbertas = ref([])
const historicoVeiculo = reactive({ saidas: [], manutencoes: [] })
const alertasManutencao = ref([])

// Mapas Gráficos
const statusColors = {
  DISPONIVEL: { border: 'border-emerald-500', badge: 'bg-emerald-100 text-emerald-800' },
  EM_USO: { border: 'border-yellow-500', badge: 'bg-yellow-100 text-yellow-800' },
  EM_MANUTENCAO: { border: 'border-red-500', badge: 'bg-red-100 text-red-800' },
  INATIVO: { border: 'border-slate-500', badge: 'bg-slate-100 text-slate-800' }
}

// Filtros & Controle de Estado
const filtersFrota = reactive({ status: '', tipo: '' })
const selectedVeiculoHistorico = ref('')
const selectedSaida = ref(null)

// Modais
const cadastroVeiculoModal = ref(false)
const retornoModal = ref(false)
const manutencaoModal = ref(false)

// Forms Model
const formVeiculo = reactive({
  veiculo_placa: '', veiculo_marca: '', veiculo_modelo: '', veiculo_ano: new Date().getFullYear(),
  veiculo_tipo: 'CARRO', veiculo_cor: '', veiculo_km_atual: 0, veiculo_renavam: ''
})

const formSaida = reactive({
  veiculo_id: '', motorista_id: '', saida_destino: '', saida_finalidade: '', 
  saida_data_hora: '', km_saida: 0
})

const formRetorno = reactive({
  retorno_data_hora: '', km_retorno: 0
})

const formManut = reactive({
  veiculo_id: '', manut_tipo: 'PREVENTIVA', manut_descricao: '', 
  manut_data: new Date().toISOString().substring(0, 10), manut_valor: null, 
  manut_proxima: '', manut_fornecedor: ''
})

// Lifecycle & Watchers
onMounted(() => {
  fetchFrota()
})

watch(activeTab, (newTab) => {
  errorMsg.value = ''
  if (newTab === 'frota') fetchFrota()
  if (newTab === 'saidas') { fetchVeiculosDisponiveis(); fetchSaidasAbertas() }
  if (newTab === 'manutencoes') { fetchFrota(); if (selectedVeiculoHistorico.value) fetchHistorico() }
  if (newTab === 'alertas') fetchAlertas()
  nextTick(() => lucide.createIcons())
})

// Utilities
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const [y, m, d] = dateStr.split('T')[0].split('-')
  return `${d}/${m}/${y}`
}
const formatDateTime = (dtStr) => {
  if (!dtStr) return '-'
  const date = new Date(dtStr)
  return date.toLocaleString('pt-BR').substring(0, 16)
}
const formatCurrency = (val) => {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val)
}

// ==========================================
// API Calls 
// ==========================================
const fetchFrota = async () => {
  isLoading.value = true; errorMsg.value = ''
  try {
    const res = await api.get('/api/v3/frotas/veiculos', { params: filtersFrota })
    veiculos.value = res.data.veiculos
  } catch (err) {
    errorMsg.value = 'Falha ao buscar frota.'
  } finally { isLoading.value = false; nextTick(() => lucide.createIcons()) }
}

const fetchVeiculosDisponiveis = async () => {
  try {
    const res = await api.get('/api/v3/frotas/veiculos/disponiveis')
    veiculosDisponiveis.value = res.data.veiculos
    if(res.data.veiculos.length) {
      // Sincroniza a data global ao entrar na tela pra ajudar o usuario
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      formSaida.saida_data_hora = now.toISOString().slice(0, 16);
    }
  } catch (err) {
    errorMsg.value = 'Falha ao buscar veículos disponíveis.'
  }
}

const fetchSaidasAbertas = async () => {
  try {
    const res = await api.get('/api/v3/frotas/saidas/abertas')
    saidasAbertas.value = res.data.saidas
  } catch (err) {
    errorMsg.value = 'Falha ao buscar saídas em aberto.'
  }
}

const fetchHistorico = async () => {
  if (!selectedVeiculoHistorico.value) return
  isLoading.value = true; errorMsg.value = '';
  try {
    const res = await api.get(`/api/v3/frotas/veiculos/${selectedVeiculoHistorico.value}/historico`)
    historicoVeiculo.manutencoes = res.data.manutencoes
    historicoVeiculo.saidas = res.data.saidas
  } catch(err) {
    errorMsg.value = 'Falha ao buscar histórico do veículo'
  } finally { isLoading.value = false }
}

const fetchAlertas = async () => {
  isLoading.value = true;
  try {
    const res = await api.get('/api/v3/frotas/manutencao/proximas')
    alertasManutencao.value = res.data.veiculos
  } catch(err) {
    errorMsg.value = 'Falha ao buscar alertas.'
  } finally { isLoading.value = false }
}

// ==========================================
// Actions & Forms 
// ==========================================

const abrirCadastroVeiculo = () => {
  Object.assign(formVeiculo, {
    veiculo_placa: '', veiculo_marca: '', veiculo_modelo: '', veiculo_ano: new Date().getFullYear(),
    veiculo_tipo: 'CARRO', veiculo_cor: '', veiculo_km_atual: 0, veiculo_renavam: ''
  })
  cadastroVeiculoModal.value = true
}

const salvarVeiculo = async () => {
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.post('/api/v3/frotas/veiculos', formVeiculo)
    cadastroVeiculoModal.value = false
    fetchFrota()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao cadastrar veículo.'
  } finally { isSaving.value = false }
}

const onVeiculoSaidaChange = () => {
  const v = veiculosDisponiveis.value.find(ve => ve.VEICULO_ID === formSaida.veiculo_id)
  if (v) formSaida.km_saida = v.VEICULO_KM_ATUAL
}

const registrarSaida = async () => {
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.post('/api/v3/frotas/saidas', formSaida)
    // Limpar o form e recarregar
    formSaida.veiculo_id = ''; formSaida.motorista_id = ''; 
    formSaida.saida_destino = ''; formSaida.saida_finalidade = '';
    fetchVeiculosDisponiveis()
    fetchSaidasAbertas()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao registrar saída.'
  } finally { isSaving.value = false }
}

const abrirRetornoModal = (s) => {
  selectedSaida.value = s
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  formRetorno.retorno_data_hora = now.toISOString().slice(0, 16);
  formRetorno.km_retorno = s.KM_SAIDA // Sugere o inicial
  retornoModal.value = true
}

const salvarRetorno = async () => {
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.patch(`/api/v3/frotas/saidas/${selectedSaida.value.SAIDA_ID}/retorno`, formRetorno)
    retornoModal.value = false
    fetchSaidasAbertas()
    fetchVeiculosDisponiveis()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao registrar retorno.'
  } finally { isSaving.value = false }
}

const abrirManutencaoModal = () => {
  formManut.veiculo_id = selectedVeiculoHistorico.value
  formManut.manut_tipo = 'PREVENTIVA'; formManut.manut_descricao = '';
  formManut.manut_data = new Date().toISOString().substring(0, 10);
  formManut.manut_valor = null; formManut.manut_proxima = ''; formManut.manut_fornecedor = '';
  manutencaoModal.value = true
}

const salvarManutencao = async () => {
  isSaving.value = true; errorMsg.value = ''
  try {
    await api.post('/api/v3/frotas/manutencao', formManut)
    manutencaoModal.value = false
    fetchHistorico()
  } catch (err) {
    errorMsg.value = err.response?.data?.erro || 'Erro ao registrar manutenção.'
  } finally { isSaving.value = false }
}

const abrirManutencaoFromAlerta = (veiculoId) => {
  // Troca de tab e joga o ID pra lá
  selectedVeiculoHistorico.value = veiculoId
  activeTab.value = 'manutencoes'
}
</script>
