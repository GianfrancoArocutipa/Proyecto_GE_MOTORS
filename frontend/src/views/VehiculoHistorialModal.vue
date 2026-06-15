<template>
  <BaseModal :show="show" @close="$emit('update:show', false)">
    <template #header>
      <h3 class="text-lg font-medium text-gray-900">
        Historial del Vehículo: {{ vehicle?.placa }}
      </h3>
    </template>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-4">
      <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        <button
          @click="activeTab = 'ordenes'"
          :class="[
            activeTab === 'ordenes'
              ? 'border-indigo-500 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
          ]"
        >
          Órdenes de Trabajo
        </button>
        <button
          @click="activeTab = 'diagnosticos'"
          :class="[
            activeTab === 'diagnosticos'
              ? 'border-indigo-500 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
          ]"
        >
          Diagnósticos OBD-II
        </button>
      </nav>
    </div>

    <!-- Contenido: Órdenes de Trabajo -->
    <div v-if="activeTab === 'ordenes'">
      <div v-if="loadingHistorial" class="text-center py-6">Cargando historial de órdenes...</div>
      <div v-else-if="historial.length === 0" class="text-center py-6 text-gray-500">
        Este vehículo no tiene órdenes de trabajo previas.
      </div>
      <div v-else class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
        <div v-for="ot in historial" :key="ot.id" class="border rounded-lg p-4 bg-gray-50 relative overflow-hidden">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>
          <div class="flex justify-between text-xs text-gray-500 mb-2">
            <span>{{ formatDate(ot.created_at) }}</span>
            <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">OT #{{ ot.numero_ot }}</span>
          </div>
          <div class="flex justify-between items-start mb-2">
            <div class="font-medium text-gray-800 text-sm flex-1 mr-4">
              Problema: <span class="font-normal">{{ ot.descripcion_problema }}</span>
            </div>
            <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-bold shrink-0">
              {{ formatEstado(ot.estado) }}
            </span>
          </div>
          <div v-if="ot.mecanicos && ot.mecanicos.length > 0" class="text-xs text-gray-600 mb-2">
            <span class="font-medium">Mecánicos:</span> {{ ot.mecanicos.map(m => m.nombre).join(', ') }}
          </div>
          <div v-if="ot.repuestos && ot.repuestos.length > 0" class="text-xs bg-white border border-gray-200 p-2 rounded mt-2">
            <div class="font-medium text-gray-700 mb-1">Repuestos instalados:</div>
            <ul class="list-disc pl-4 text-gray-600">
              <li v-for="(rep, i) in ot.repuestos" :key="i">
                {{ rep.cantidad }}x {{ rep.nombre }}
              </li>
            </ul>
          </div>
          <div v-if="ot.fecha_cierre" class="text-xs text-green-600 mt-2 font-medium">
            Entregado el: {{ formatDate(ot.fecha_cierre) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Contenido: Diagnósticos -->
    <div v-if="activeTab === 'diagnosticos'">
      <div v-if="loadingDiag" class="text-center py-6">Cargando diagnósticos...</div>
      <div v-else-if="diagnosticos.length === 0" class="text-center py-6 text-gray-500">
        No se registran diagnósticos previos para este vehículo.
      </div>
      <div v-else class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
        <div v-for="d in diagnosticos" :key="d.id" class="border rounded-lg p-4 bg-gray-50 relative overflow-hidden">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-400"></div>
          <div class="flex justify-between text-xs text-gray-500 mb-2">
            <span>{{ formatDate(d.created_at) }}</span>
            <span class="font-bold">OT #{{ d.numero_ot || d.orden_id }}</span>
          </div>
          <div v-if="d.mecanico_nombre" class="text-xs text-gray-600 mb-2">
            <span class="font-medium">Mecánico:</span> {{ d.mecanico_nombre }}
          </div>
          <div class="flex flex-wrap gap-2 mb-2">
            <span 
              v-for="(code, idx) in normalizeCodigos(d.codigos_falla)" 
              :key="idx"
              class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold"
            >
              {{ code }}
            </span>
            <span v-if="normalizeCodigos(d.codigos_falla).length === 0" class="text-xs text-gray-400 italic">
              Sin códigos de falla registrados
            </span>
          </div>
          <p v-if="d.observaciones && d.observaciones !== 'Sin observaciones'" class="text-sm text-gray-700 italic">"{{ d.observaciones }}"</p>
          <p v-else class="text-sm text-gray-400 italic">Sin observaciones</p>
        </div>
      </div>
    </div>

    <template #footer>
      <BaseButton variant="outline" @click="$emit('update:show', false)">Cerrar</BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { vehiculoService } from '@/services/vehiculoService'
import { useDataFetch } from '@/composables/useDataFetch'
import BaseModal from '@/components/shared/BaseModal.vue'
import BaseButton from '@/components/shared/BaseButton.vue'

const props = defineProps({
  show: Boolean,
  vehicle: Object
})

const activeTab = ref('ordenes')

const historial = ref([])
const diagnosticos = ref([])

const { loading: loadingHistorial, execute: fetchHistory } = useDataFetch((id) => vehiculoService.getHistorial(id))
const { loading: loadingDiag, execute: fetchDiag } = useDataFetch((id) => vehiculoService.getDiagnosticos(id))

watch(() => props.show, async (isShown) => {
  if (isShown && props.vehicle) {
    activeTab.value = 'ordenes'
    
    // Cargar historial de OTs
    try {
      const resHist = await fetchHistory(props.vehicle.id)
      if (resHist && resHist.success) {
        historial.value = resHist.data || []
      } else {
        historial.value = []
      }
    } catch (err) {
      historial.value = []
    }

    // Cargar diagnósticos
    try {
      const resDiag = await fetchDiag(props.vehicle.id)
      if (resDiag && resDiag.success) {
        diagnosticos.value = resDiag.data || []
      } else {
        diagnosticos.value = []
      }
    } catch (err) {
      diagnosticos.value = []
    }
  }
})

function formatEstado(estado) {
  const map = {
    'diagnostico': 'En Diagnóstico',
    'reparacion': 'En Reparación',
    'esperando_repuesto': 'Esperando Repuesto',
    'control_calidad': 'Control de Calidad',
    'entregado': 'Entregado'
  }
  return map[estado] || estado
}

function normalizeCodigos(codigos) {
  if (!codigos) return []
  if (typeof codigos === 'string') {
    try { codigos = JSON.parse(codigos) } catch { return [codigos] }
  }
  if (Array.isArray(codigos)) return codigos.filter(c => c)
  if (typeof codigos === 'object') return Object.values(codigos).filter(c => c)
  return []
}

function formatDate(dateStr) {
  if (!dateStr) return 'Fecha no disponible'
  const d = new Date(dateStr.replace(' ', 'T'))
  return isNaN(d.getTime()) 
    ? 'Fecha inválida' 
    : d.toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
