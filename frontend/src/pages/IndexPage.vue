<template>
  <q-page class="workspace"
    ><div class="page-heading"
      ><div
        ><span class="eyebrow">TU TÓPICO, EN UN VISTAZO</span
        ><h1>Resumen general</h1
        ><p>Un buen día para cuidar de nuestra comunidad.</p></div
      ><q-btn
        color="primary"
        unelevated
        no-caps
        icon="add"
        label="Solicitar atención"
        to="/solicitudes?nueva=1"
    /></div>
    <q-banner v-if="error" class="bg-red-1 text-red-9 q-mb-lg" rounded
      >{{ error }}
      <template #action
        ><q-btn flat label="Reintentar" @click="load" /></template></q-banner
    ><q-inner-loading :showing="loading" />
    <template v-if="data"
      ><div class="stats-grid"
        ><div v-for="stat in cards" :key="stat.label" class="stat-card"
          ><div class="row justify-between items-center"
            ><span>{{ stat.label }}</span
            ><div
              class="stat-icon"
              :style="{ background: stat.background, color: stat.color }"
              ><q-icon :name="stat.icon" size="23px" /></div></div
          ><strong>{{ stat.value }}</strong
          ><small>{{ stat.note }}</small></div
        ></div
      >
      <div class="dashboard-grid"
        ><section class="panel"
          ><div class="panel-heading"
            ><div
              ><h2>Solicitudes por atender</h2
              ><p>La próxima atención empieza aquí.</p></div
            ><q-btn
              flat
              no-caps
              color="primary"
              label="Ver todas"
              to="/solicitudes"
              icon-right="arrow_forward" /></div
          ><div v-if="!data.requests.length" class="empty-state"
            ><q-icon name="task_alt" size="44px" /><h3>Todo al día</h3
            ><p>No hay solicitudes pendientes en tu espacio.</p></div
          ><q-list v-else separator
            ><q-item
              v-for="item in data.requests"
              :key="item.id"
              class="request-row"
              ><q-item-section avatar
                ><q-avatar class="patient-avatar">{{
                  item.patient_name.slice(0, 2).toUpperCase()
                }}</q-avatar></q-item-section
              ><q-item-section
                ><q-item-label class="text-weight-medium">{{
                  item.patient_name
                }}</q-item-label
                ><q-item-label caption>{{
                  item.reason
                }}</q-item-label></q-item-section
              ><q-item-section side
                ><q-badge
                  :color="item.priority === 'urgent' ? 'red-1' : 'cyan-1'"
                  :text-color="item.priority === 'urgent' ? 'red-8' : 'cyan-9'"
                  >{{ labels[item.priority] }}</q-badge
                ><small class="q-mt-sm">{{
                  date(item.created_at)
                }}</small></q-item-section
              ></q-item
            ></q-list
          ></section
        >
        <section class="panel"
          ><div class="panel-heading"
            ><div
              ><h2>{{
                clinical ? "Alertas de inventario" : "Tu espacio de atención"
              }}</h2
              ><p>{{
                clinical
                  ? "Anticípate a lo que hace falta."
                  : "Consulta y sigue tus registros."
              }}</p></div
            ><q-icon
              name="notifications_none"
              color="primary"
              size="25px" /></div
          ><template v-if="clinical"
            ><div v-if="!data.alerts.length" class="empty-state"
              ><q-icon name="inventory_2" size="44px" /><h3
                >Sin alertas de inventario</h3
              ><p>Los avisos de stock y vencimiento aparecerán aquí.</p></div
            ><div
              v-for="alert in data.alerts"
              :key="alert.key"
              class="alert-item"
              ><q-icon
                :name="alert.type === 'stock' ? 'inventory_2' : 'event_busy'"
                color="orange-8"
                size="23px"
              /><div
                ><strong>{{ alert.name }}</strong
                ><p>{{ alert.message }}</p></div
              ></div
            ><q-btn
              flat
              no-caps
              color="primary"
              label="Gestionar medicamentos"
              to="/medicamentos"
              class="q-ma-md" /></template
          ><div v-else class="q-pa-lg"
            ><p>Las solicitudes y sus estados se mantienen en un mismo lugar.</p
            ><q-btn
              outline
              no-caps
              color="primary"
              label="Ver mis solicitudes"
              to="/solicitudes" /></div></section
      ></div>
      <section class="care-banner"
        ><div class="care-banner-icon"
          ><q-icon name="health_and_safety" size="40px" /></div
        ><div
          ><span class="eyebrow">ATENCIÓN CONECTADA</span
          ><h2>Del primer aviso al seguimiento.</h2
          ><p
            >Solicitud, atención, medicamentos y derivación en un mismo
            historial.</p
          ></div
        ><q-btn
          flat
          no-caps
          color="primary"
          label="Ver atenciones"
          icon-right="arrow_forward"
          :to="
            session.user?.role === 'teacher' ? '/solicitudes' : '/atenciones'
          " /></section></template
  ></q-page>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { api, session, labels, date, type Row } from "@/services/api";
const data = ref<Row | null>(null);
const loading = ref(true);
const error = ref("");
const clinical = computed(() =>
  ["coordinator", "staff"].includes(session.user?.role)
);
const cards = computed(() => [
  {
    label:
      session.user?.role === "teacher"
        ? "Solicitudes de hoy"
        : "Atenciones de hoy",
    value: data.value?.today ?? 0,
    note: "Registradas en el día",
    icon: "medical_services",
    background: "#e0f4f6",
    color: "#00838f"
  },
  {
    label: "Solicitudes pendientes",
    value: data.value?.pending ?? 0,
    note: "Esperando una atención",
    icon: "schedule",
    background: "#fff3dc",
    color: "#ba8525"
  },
  {
    label: "En atención",
    value: data.value?.active ?? 0,
    note: "Cuidados en curso",
    icon: "favorite_border",
    background: "#e8eefc",
    color: "#5279c0"
  },
  {
    label:
      session.user?.role === "teacher"
        ? "Solicitudes finalizadas"
        : "Derivaciones pendientes",
    value:
      session.user?.role === "teacher"
        ? (data.value?.completed ?? 0)
        : (data.value?.referrals ?? 0),
    note:
      session.user?.role === "teacher"
        ? "Casos reportados ya atendidos"
        : "Seguimientos por completar",
    icon: "moving",
    background: "#f0eafa",
    color: "#8d64af"
  }
]);
async function load() {
  loading.value = true;
  error.value = "";
  try {
    data.value = await api("/dashboard");
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>
