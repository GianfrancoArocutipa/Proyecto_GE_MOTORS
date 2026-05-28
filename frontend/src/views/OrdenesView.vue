<template>
  <div class="p-6 shadow-md rounded-xl bg-white">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900">
        Gestión de Órdenes de Trabajo
      </h2>
      <BaseButton
        variant="primary"
        size="md"
        @click="abrirModalCreacion"
      >
        Nueva Orden
      </BaseButton>
    </div>

    <BaseAlert
      v-if="alert"
      :show="true"
      :variant="alert.type"
      :message="alert.message"
      @update:show="show => { if (!show) alert = null }"
    />

    <!-- Skeleton Loader -->
    <div v-if="listLoading" class="space-y-4">
      <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
      <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
      <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
      <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!listLoading && (!ordenesData || ordenesData.length === 0)" class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="mt-4 text-lg text-gray-500">No hay órdenes de trabajo registradas</p>
      <p class="mt-2 text-sm text-gray-400">Haz clic en "Nueva Orden" para comenzar</p>
    </div>

    <!-- Data Table -->
    <div v-else>
      <BaseTable
        :columns="ordenesColumns"
        :data="ordenes"
        :loading="false"
        @row-click="verDetalle"
      />
    </div>

    <!-- State Transition Modal -->
    <TransicionEstadoModal
      v-model:show="showTransicionModal"
      :ot-id="otSeleccionada?.id"
      :estado-actual="otSeleccionada?.estado"
      :nuevo-estado="estadoObjetivo"
      :presupuesto-aprobado="otSeleccionada?.presupuesto_aprobado || false"
      :loading="transicionLoading"
      @confirmar="ejecutarTransicion"
      @close="resetTransicion"
      v-if="showTransicionModal"
    />

    <!-- Create Order Modal -->
    <BaseModal v-model:show="showCreateModal" @close="resetCreateForm">
      <template #header>
        <h3 class="text-lg font-medium text-gray-900">Nueva Orden de Trabajo</h3>
      </template>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Número de OT</label>
          <input v-model="orderForm.numero_ot" type="text" class="w-full px-3 py-2 border rounded-md" placeholder="Ej: OT-1001" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Cliente</label>
          <select v-model="orderForm.cliente_id" class="w-full px-3 py-2 border rounded-md">
            <option value="">Seleccione un cliente...</option>
            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Vehículo</label>
          <select v-model="orderForm.vehiculo_id" class="w-full px-3 py-2 border rounded-md" :disabled="!orderForm.cliente_id">
            <option value="">{{ orderForm.cliente_id ? 'Seleccione un vehículo...' : 'Primero seleccione un cliente' }}</option>
            <option v-for="v in clientVehicles" :key="v.id" :value="v.id">{{ v.marca }} {{ v.modelo }} ({{ v.placa }})</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Descripción del Problema</label>
          <textarea v-model="orderForm.descripcion_problema" rows="3" class="w-full px-3 py-2 border rounded-md" placeholder="Describa la falla reportada..."></textarea>
        </div>

        <div v-if="orderForm.cliente_id && clientVehicles.length === 0 && !loadingVehicles" class="text-xs text-amber-600 bg-amber-50 p-2 rounded">
          Este cliente no tiene vehículos registrados.
        </div>
      </div>

      <template #footer>
        <BaseButton variant="outline" @click="showCreateModal = false">Cancelar</BaseButton>
        <BaseButton 
          variant="primary" 
          :loading="createLoading" 
          :disabled="!orderForm.numero_ot || !orderForm.vehiculo_id"
          @click="handleCreate"
        >
          Crear Orden
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Detail Modal -->
    <BaseModal v-model:show="showDetailModal" @close="resetDetail">
      <template #header>
        <h3 class="text-lg font-medium text-gray-900">
          Detalle de Orden #{{ otSeleccionada?.numero_ot || '' }}
        </h3>
      </template>

      <div v-if="otSeleccionada">
        <!-- Pipeline -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-700 mb-3">Estado actual del proceso</h4>
          <OrdenEstadoPipeline
            :estado-actual="otSeleccionada.estado"
            :presupuesto-aprobado="otSeleccionada.presupuesto_aprobado || false"
            :ot-id="otSeleccionada.id"
            @transicion-estado="abrirTransicion"
          />
        </div>

        <!-- Order details -->
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-500">Cliente</p>
              <p class="text-sm text-gray-900">{{ otSeleccionada.cliente?.nombre || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">Vehículo</p>
              <p class="text-sm text-gray-900">
                {{ otSeleccionada.vehiculo ? `${otSeleccionada.vehiculo.marca} ${otSeleccionada.vehiculo.modelo} - ${otSeleccionada.vehiculo.placa}` : 'N/A' }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-500">Fecha de ingreso</p>
              <p class="text-sm text-gray-900">{{ formatDate(otSeleccionada.fecha_ingreso) }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-900">{{ otSeleccionada.mecanico?.nombre || 'Sin mecánicos asignados' }}</p>
              <BaseButton 
                v-if="userRole === 'administrador' && otSeleccionada.estado !== 'entregado' && (!otSeleccionada.mecanicos_asignados || otSeleccionada.mecanicos_asignados.length === 0)"
                variant="link" 
                size="sm" 
                class="mt-1 p-0 h-auto"
                @click="abrirAsignarMecanico"
              >
                Asignar ahora
              </BaseButton>
            </div>
          </div>

          <!-- Mecánicos Asignados List -->
          <div v-if="otSeleccionada.mecanicos_asignados && otSeleccionada.mecanicos_asignados.length > 0" class="pt-4 border-t border-gray-200">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-medium text-gray-700">Mecánicos Asignados</h4>
              <BaseButton 
                v-if="userRole === 'administrador' && otSeleccionada.estado !== 'entregado'" 
                variant="link" size="sm" class="p-0" @click="abrirAsignarMecanico"
              >
                + Añadir Mecánico
              </BaseButton>
            </div>
            <ul class="space-y-2">
              <li v-for="m in otSeleccionada.mecanicos_asignados" :key="m.asignacion_id" class="flex justify-between items-center bg-gray-50 p-2 rounded border border-gray-100">
                <div>
                  <span class="text-sm font-medium text-gray-800">{{ m.nombre }} {{ m.apellido }}</span>
                </div>
                <div class="flex items-center space-x-2">
                  <span class="text-xs text-gray-500">Horas:</span>
                  <input v-if="userRole === 'administrador' || authStore.user?.id === m.mecanico_id" 
                         type="number" step="0.5" min="0" class="w-16 px-1 py-1 text-sm border rounded" 
                         v-model.number="m.horas_trabajadas" />
                  <span v-else class="text-sm font-medium text-gray-900 w-12 text-right">{{ m.horas_trabajadas }}</span>
                  <BaseButton v-if="userRole === 'administrador' || authStore.user?.id === m.mecanico_id" 
                              variant="primary" size="sm" class="px-2 py-1 h-auto text-xs" 
                              @click="guardarHorasMecanico(m)" :loading="m.saving">Guardar</BaseButton>
                </div>
              </li>
            </ul>
          </div>

          <!-- Budget status -->
          <div v-if="otSeleccionada.presupuesto_id" class="pt-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-gray-500">Presupuesto</p>
              <span :class="[
                'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                presupuestoAprobado ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
              ]">
                {{ presupuestoAprobado ? 'Aprobado' : 'Pendiente de aprobación' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <BaseButton
          variant="outline"
          size="md"
          @click="showDetailModal = false"
        >
          Cerrar
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Assign Mechanic Modal -->
    <BaseModal v-model:show="showAssignMecanicoModal" @close="showAssignMecanicoModal = false">
      <template #header>
        <h3 class="text-lg font-medium text-gray-900">Asignar Mecánico a OT #{{ otSeleccionada?.numero_ot }}</h3>
      </template>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Seleccionar Mecánico</label>
          <select v-model="mecanicoForm.mecanico_id" class="w-full px-3 py-2 border rounded-md">
            <option value="">Seleccione...</option>
            <option v-for="m in listaMecanicos" :key="m.id" :value="m.id">
              {{ m.nombre }} {{ m.apellido }} ({{ m.ots_activas }} OTs activas)
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Horas Estimadas / Iniciales</label>
          <input v-model.number="mecanicoForm.horas_trabajadas" type="number" step="0.5" class="w-full px-3 py-2 border rounded-md" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="outline" @click="showAssignMecanicoModal = false">Cancelar</BaseButton>
        <BaseButton 
          variant="primary" 
          :loading="assignLoadingState" 
          :disabled="!mecanicoForm.mecanico_id"
          @click="handleAssignMecanico"
        >
          Confirmar Asignación
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { ordenService } from '@/services/ordenService'
import { clienteService } from '@/services/clienteService'
import { vehiculoService } from '@/services/vehiculoService'
import { useDataFetch } from '@/composables/useDataFetch'
import BaseTable from '@/components/shared/BaseTable.vue'
import BaseModal from '@/components/shared/BaseModal.vue'
import BaseButton from '@/components/shared/BaseButton.vue'
import BaseAlert from '@/components/shared/BaseAlert.vue'
import OrdenEstadoPipeline from '@/components/ordenes/OrdenEstadoPipeline.vue'
import TransicionEstadoModal from '@/components/ordenes/TransicionEstadoModal.vue'
import { usuarioService } from '@/services/usuarioService'

const authStore = useAuthStore()
const notificacionesStore = useNotificacionesStore()

// Propiedad computada para el rol, asegura reactividad y evita errores de undefined
const userRole = computed(() => {
  return authStore.user?.rol || authStore.userRole || ''
})

const alert = ref(null)
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showTransicionModal = ref(false)
const showAssignMecanicoModal = ref(false)

const otSeleccionada = ref(null)
const estadoObjetivo = ref(null)
const transicionLoading = ref(false)
const assignLoading = ref(false)

const listaMecanicos = ref([])
const mecanicoForm = reactive({ mecanico_id: '', horas_trabajadas: 0 })

// Form state
const orderForm = reactive({
  numero_ot: '',
  cliente_id: '',
  vehiculo_id: '',
  descripcion_problema: ''
})
const clients = ref([])
const clientVehicles = ref([])
const loadingVehicles = ref(false)

const presupuestoAprobado = computed(() => {
  return otSeleccionada.value?.presupuesto_aprobado || false
})

const ordenesColumns = [
  { key: 'numero_ot', label: 'OT', width: 'quarter' },
  { key: 'cliente_nombre', label: 'Cliente', width: 'half' },
  {
    key: 'vehiculo_info',
    label: 'Vehículo',
    width: 'half',
    format: (value, row) => {
      if (row.vehiculo) {
        return `${row.vehiculo.marca} ${row.vehiculo.modelo} - ${row.vehiculo.placa}`
      }
      return 'N/A'
    }
  },
  {
    key: 'estado',
    label: 'Estado',
    width: 'quarter',
    format: (value) => {
      const labels = {
        diagnostico: 'Diagnóstico',
        reparacion: 'Reparación',
        esperando_repuesto: 'Esperando Repuesto',
        control_calidad: 'Control Calidad',
        entregado: 'Entregado'
      }
      return labels[value] || value
    }
  }
]

function formatDate(dateString) {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString('es-PE')
}

// ===== Data fetching for list =====
const { loading: listLoading, data: ordenesData, error: listError, execute: fetchOrdenesList } = 
  useDataFetch(ordenService.getAll)

// ===== Mutation for creating order =====
const { loading: createLoading, execute: createOrder } = useDataFetch(ordenService.create)

// ===== Mutation for assigning mechanic =====
const { loading: assignLoadingState, error: assignError, execute: assignMecanicoAction } = useDataFetch(ordenService.asignarMecanico)

// ===== Mutation for changing state =====
const { loading: transicionLoadingState, error: transicionError, execute: cambiarEstado } = 
  useDataFetch(ordenService.cambiarEstado)

// Update the ordenes ref from the composable's data
const ordenes = ref([])

// Watch client selection to fetch their vehicles
watch(() => orderForm.cliente_id, async (newClientId) => {
  orderForm.vehiculo_id = ''
  clientVehicles.value = []
  if (newClientId) {
    loadingVehicles.value = true
    try {
      const response = await vehiculoService.getAll({ cliente_id: newClientId })
      clientVehicles.value = response.data || []
    } finally {
      loadingVehicles.value = false
    }
  }
})

watch(() => ordenesData.value, (val) => {
  if (val && val.data) {
    ordenes.value = val.data.map(orden => ({
      ...orden,
      cliente_nombre: orden.cliente?.nombre || 'N/A'
    }))
  }
})

// Handle errors
watch(listError, (err) => {
  if (err) {
    alert.value = { type: 'error', message: err.message || 'Error al obtener órdenes' }
    notificacionesStore.addNotification({
      type: 'error',
      message: err.message || 'Error inesperado',
      timeout: 5000
    })
  } else {
    alert.value = null
  }
})

watch(assignError, (err) => {
  if (err) {
    notificacionesStore.addNotification({
      type: 'error',
      message: err.message || 'Error al asignar mecánico',
      timeout: 5000
    })
  }
})

watch(transicionError, (err) => {
  if (err) {
    notificacionesStore.addNotification({
      type: 'error',
      message: err.message || 'Error inesperado',
      timeout: 5000
    })
  }
})

async function fetchOrdenes() {
  try {
    await fetchOrdenesList()
  } catch (err) {
    // Error handled by watcher
  }
}

async function abrirAsignarMecanico() {
  try {
    const response = await usuarioService.getCargaMecanicos()
    if (response && response.success) {
      listaMecanicos.value = response.data || []
      showAssignMecanicoModal.value = true
    }
  } catch (err) {
    console.error('Fallo al recuperar lista de mecánicos:', err)
  }
}

async function handleAssignMecanico() {
  try {
    await assignMecanicoAction(otSeleccionada.value.id, mecanicoForm)
    notificacionesStore.addNotification({ type: 'success', message: 'Mecánico asignado correctamente' })
    showAssignMecanicoModal.value = false
    
    mecanicoForm.mecanico_id = ''
    mecanicoForm.horas_trabajadas = 0
    await fetchOrdenes()
    
    // Refrescar modal con info actualizada
    if (otSeleccionada.value) {
      verDetalle({ id: otSeleccionada.value.id })
    }
  } catch (err) {
  }
}

async function guardarHorasMecanico(mecanico) {
  mecanico.saving = true
  try {
    await ordenService.actualizarHorasMecanico(otSeleccionada.value.id, mecanico.mecanico_id, {
      horas_trabajadas: mecanico.horas_trabajadas
    })
    notificacionesStore.addNotification({ type: 'success', message: 'Horas actualizadas' })
  } catch (err) {
    notificacionesStore.addNotification({ type: 'error', message: err.message || 'Error al actualizar horas' })
  } finally {
    mecanico.saving = false
  }
}

async function abrirModalCreacion() {
  try {
    const response = await clienteService.getAll()
    clients.value = response.data || []
    showCreateModal.value = true
  } catch (err) {
    notificacionesStore.addNotification({ type: 'error', message: 'No se pudieron cargar los clientes' })
  }
}

async function handleCreate() {
  try {
    await createOrder(orderForm)
    notificacionesStore.addNotification({ type: 'success', message: 'Orden creada exitosamente' })
    showCreateModal.value = false
    resetCreateForm()
    await fetchOrdenes()
  } catch (err) {
    // Error handled by composable
  }
}

function resetCreateForm() {
  orderForm.numero_ot = ''
  orderForm.cliente_id = ''
  orderForm.vehiculo_id = ''
  orderForm.descripcion_problema = ''
  clientVehicles.value = []
}

async function verDetalle(orden) {
  try {
    const res = await ordenService.getById(orden.id)
    if (res && res.success) {
      otSeleccionada.value = res.data
      showDetailModal.value = true
    }
  } catch (e) {
    notificacionesStore.addNotification({ type: 'error', message: 'Error al cargar detalles de la orden' })
  }
}

function abrirTransicion({ otId, nuevoEstado }) {
  estadoObjetivo.value = nuevoEstado
  showTransicionModal.value = true
}

function resetTransicion() {
  showTransicionModal.value = false
  estadoObjetivo.value = null
}

function resetDetail() {
  showDetailModal.value = false
  otSeleccionada.value = null
}

async function ejecutarTransicion({ otId, nuevoEstado, observaciones }) {
  transicionLoading.value = true
  try {
    await cambiarEstado(otId, {
      estado: nuevoEstado,
      observaciones: observaciones
    })
    notificacionesStore.addNotification({
      type: 'success',
      message: 'Estado actualizado correctamente',
      timeout: 3000
    })
    // Refresh the list
    await fetchOrdenesList()
    // If the selected order is the one we just updated, refresh the detail modal
    if (otSeleccionada.value?.id === otId) {
      // We could fetch the specific order, but for simplicity we'll just refresh the list and then
      // the detail modal will show the updated data from the list (since we map over the list)
      // However, the detail modal uses otSeleccionada which is a ref to an order from the list.
      // We'll need to update otSeleccionada with the updated order from the list.
      // We'll do a timeout to let the list update and then find the order.
      setTimeout(() => {
        const updatedOrder = ordenes.value.find(o => o.id === otId)
        if (updatedOrder) {
          otSeleccionada.value = updatedOrder
        }
      }, 100)
    }
  } catch (err) {
    // Error handled by watcher
  } finally {
    transicionLoading.value = false
    showTransicionModal.value = false
  }
}

onMounted(() => {
  fetchOrdenes()
})
</script>