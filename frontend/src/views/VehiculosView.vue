<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Gestión de Vehículos</h2>
      <BaseButton variant="primary" @click="openCreate">Nuevo Vehículo</BaseButton>
    </div>

    <div v-if="loading" class="text-center py-8">
      Cargando datos...
    </div>

    <BaseTable :columns="vehicleColumns" :data="vehicles" @row-click="openDetail" />

    <VehiculoFormModal 
      v-model:show="showForm" 
      :data="editingVehicle" 
      :clients="clients"
      @saved="fetchData" 
    />
    
    <VehiculoHistorialModal 
      v-model:show="showDiag" 
      :vehicle="selectedVehicle" 
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { vehiculoService } from '@/services/vehiculoService'
import { clienteService } from '@/services/clienteService'
import { useDataFetch } from '@/composables/useDataFetch'
import { useAuthStore } from '@/stores/auth'
import { useNotificacionesStore } from '@/stores/notificaciones'
import BaseButton from '@/components/shared/BaseButton.vue'
import BaseTable from '@/components/shared/BaseTable.vue'
import VehiculoFormModal from '@/views/VehiculoFormModal.vue'
import VehiculoHistorialModal from '@/views/VehiculoHistorialModal.vue'

const vehicleColumns = [
  { key: 'placa', label: 'Placa' },
  { key: 'marca', label: 'Marca' },
  { key: 'modelo', label: 'Modelo' },
  { key: 'cliente_nombre', label: 'Propietario' }
]

const vehicles = ref([])
const clients = ref([])
const loading = ref(false)
const showForm = ref(false)
const showDiag = ref(false)
const editingVehicle = ref(null)
const selectedVehicle = ref(null)
const authStore = useAuthStore()
const notificacionesStore = useNotificacionesStore()

// Obtener rol de forma reactiva
const userRole = computed(() => authStore.user?.rol || authStore.userRole)
const userId = computed(() => authStore.user?.id)

// ===== Data fetching for vehicles =====
const { loading: vehiclesLoading, data: vehiclesData, error: vehiclesError, execute: fetchVehiclesList } =
  useDataFetch((params) => vehiculoService.getAll(params))

// ===== Data fetching for clients =====
const { loading: clientsLoading, data: clientsData, error: clientsError, execute: fetchClientsList } =
  useDataFetch(() => clienteService.getAll())

// Update vehicles and clients refs from composable data
watch(() => vehiclesData.value, (val) => {
  if (val && val.data) {
    vehicles.value = val.data.map(v => ({
      ...v,
      cliente_nombre: v.cliente?.nombre || 'N/A'
    }))
  }
})

watch(() => clientsData.value, (val) => {
  if (val && val.data) {
    clients.value = val.data
  }
})

// Combined loading state
watch([vehiclesLoading, clientsLoading], ([vL, cL]) => {
  loading.value = vL || cL
})

// Handle errors and show notifications
watch([vehiclesError, clientsError], ([vErr, cErr]) => {
  if (vErr) {
    notificacionesStore.addNotification({ type: 'error', message: vErr.message || 'Error al cargar vehículos', timeout: 5000 })
  }
  if (cErr) {
    notificacionesStore.addNotification({ type: 'error', message: cErr.message || 'Error al cargar clientes', timeout: 5000 })
  }
})

async function fetchData() {
  try {
    if (userRole.value === 'cliente') {
      // Si es cliente, solo pedimos sus vehículos enviando su ID
      await fetchVehiclesList({ cliente_id: userId.value })
      clients.value = authStore.user ? [authStore.user] : []
    } else {
      // Admin y mecánico cargan todo
      await fetchVehiclesList()
      await fetchClientsList()
    }
  } catch (err) {
    // Error handled by composables
  }
}

function openCreate() {
  editingVehicle.value = null
  showForm.value = true
}

function openDetail(row) {
  selectedVehicle.value = row
  showDiag.value = true
}

onMounted(fetchData)
</script>