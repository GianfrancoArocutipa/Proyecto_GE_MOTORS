<template>
  <BaseModal :show="show" @close="$emit('update:show', false)">
    <template #header>
      <h3 class="text-lg font-medium text-gray-900">
        Historial de Diagnósticos: {{ vehicle?.placa }}
      </h3>
    </template>

    <div v-if="loading" class="text-center py-6">Cargando historial...</div>
    
    <div v-else-if="diagnosticos.length === 0" class="text-center py-6 text-gray-500">
      No se registran diagnósticos previos para este vehículo.
    </div>

    <div v-else class="space-y-4">
      <div v-for="d in diagnosticos" :key="d.id" class="border rounded-lg p-4 bg-gray-50">
        <div class="flex justify-between text-xs text-gray-500 mb-2">
          <span>{{ formatDate(d.created_at) }}</span>
          <span class="font-bold">OT #{{ d.numero_ot || d.orden_id }}</span>
        </div>
        <div v-if="d.mecanico_nombre" class="text-xs text-gray-400 mb-2">
          Mecánico: {{ d.mecanico_nombre }}
        </div>
        <div class="flex flex-wrap gap-2 mb-2">
          <span 
            v-for="(code, idx) in normalizeCodigos(d.codigos_falla)" 
            :key="idx"
            class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-sm font-mono font-bold"
          >
            {{ code }}
          </span>
          <span v-if="normalizeCodigos(d.codigos_falla).length === 0" class="text-sm text-gray-400 italic">
            Sin códigos de falla registrados
          </span>
        </div>
        <p v-if="d.observaciones && d.observaciones !== 'Sin observaciones'" class="text-sm text-gray-700 italic">"{{ d.observaciones }}"</p>
        <p v-else class="text-sm text-gray-400 italic">Sin observaciones</p>
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

const diagnosticos = ref([])
const { loading, execute: fetchHistory } = useDataFetch((id) => vehiculoService.getDiagnosticos(id))

watch(() => props.show, async (isShown) => {
  if (isShown && props.vehicle) {
    try {
      const response = await fetchHistory(props.vehicle.id)
      if (response && response.success) {
        diagnosticos.value = response.data || []
      } else {
        diagnosticos.value = []
      }
    } catch (err) {
      diagnosticos.value = []
    }
  }
})

/**
 * Normaliza codigos_falla a un array plano de strings.
 * El backend puede enviar: null, [], {}, ["P0103"], {"0":"P0103"}, o un string JSON.
 */
function normalizeCodigos(codigos) {
  if (!codigos) return []
  // Si es un string (JSON stringificado), intentar parsearlo
  if (typeof codigos === 'string') {
    try {
      codigos = JSON.parse(codigos)
    } catch {
      return [codigos] // Si no es JSON válido, tratarlo como un código único
    }
  }
  // Si es un array, devolver directamente
  if (Array.isArray(codigos)) return codigos.filter(c => c)
  // Si es un objeto (ej: {0: "P0103", 1: "P0300"}), extraer los valores
  if (typeof codigos === 'object') return Object.values(codigos).filter(c => c)
  return []
}

function formatDate(dateStr) {
  if (!dateStr) return 'Fecha no disponible'
  // Reemplazar espacio por T para asegurar compatibilidad ISO en todos los navegadores
  const d = new Date(dateStr.replace(' ', 'T'))
  return isNaN(d.getTime()) 
    ? 'Fecha inválida' 
    : d.toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>