<template>
  <q-page class="workspace">
    <div class="page-heading"
      ><div
        ><span class="eyebrow">GESTIÓN DEL TÓPICO</span
        ><h1>{{ route.meta.title }}</h1
        ><p>{{ descriptions[module] }}</p></div
      ><div class="row q-gutter-sm"
        ><q-btn
          v-if="module === 'medicamentos'"
          outline
          color="primary"
          no-caps
          icon="swap_vert"
          label="Movimientos"
          @click="showMovements" /><q-btn
          v-if="canCreate"
          unelevated
          color="primary"
          no-caps
          icon="add"
          :label="createLabels[module]"
          @click="createRecord" /></div
    ></div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-lg"
      >{{ error
      }}<template #action
        ><q-btn flat no-caps label="Reintentar" @click="load" /></template
    ></q-banner>
    <section v-if="module === 'reportes'" class="panel q-mb-lg"
      ><q-form class="filter-bar" @submit="load"
        ><q-input
          outlined
          dense
          v-model="from"
          type="date"
          label="Desde"
          required /><q-input
          outlined
          dense
          v-model="to"
          type="date"
          label="Hasta"
          required /><q-select
          outlined
          dense
          v-model="patientType"
          :options="typeOptions"
          emit-value
          map-options
          label="Tipo de paciente"
          clearable /><q-btn
          type="submit"
          unelevated
          color="primary"
          label="Generar reporte"
          no-caps
          :loading="loading" /><q-btn
          outline
          color="primary"
          label="Exportar CSV"
          no-caps
          icon="download"
          @click="exportCsv"
          :disable="!rows.length" /></q-form
      ><div v-if="report" class="report-numbers"
        ><div
          v-for="stat in [
            { label: 'Atenciones', value: report.total },
            { label: 'Finalizadas', value: report.completed },
            { label: 'Derivaciones', value: report.referrals }
          ]"
          :key="stat.label"
          class="stat-card"
          ><span>{{ stat.label }}</span
          ><strong>{{ stat.value }}</strong></div
        ></div
      ></section
    >
    <section class="panel"
      ><div class="table-toolbar"
        ><q-input
          v-model="search"
          outlined
          dense
          placeholder="Buscar en los registros…"
          class="table-search"
          clearable
          ><template #prepend
            ><q-icon name="search" color="grey-6" /></template></q-input
        ><div class="row q-gutter-sm items-center"
          ><q-select
            v-if="
              ['solicitudes', 'atenciones', 'derivaciones'].includes(module)
            "
            outlined
            dense
            v-model="status"
            :options="statusOptions"
            emit-value
            map-options
            label="Estado"
            clearable
            style="min-width: 160px" /><span class="text-caption text-grey-6"
            >{{ rows.length }} registros</span
          ><q-btn
            flat
            round
            dense
            icon="refresh"
            color="grey-7"
            aria-label="Actualizar registros"
            :loading="loading"
            @click="load" /></div
      ></div>
      <q-table
        :rows="filteredRows"
        :columns="columns"
        row-key="id"
        :filter="search || ''"
        :loading="loading"
        :pagination="{ rowsPerPage: 10 }"
        :rows-per-page-options="[10, 25, 50, 0]"
        class="data-table"
        flat
        no-data-label="Aún no hay registros para mostrar"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Filas por página"
      >
        <template #body-cell-status="props"
          ><q-td :props="props"
            ><q-badge
              :color="statusColors[props.row.status] || 'grey-7'"
              outline
              >{{ labels[props.row.status] || props.row.status }}</q-badge
            ></q-td
          ></template
        >
        <template #body-cell-priority="props"
          ><q-td :props="props"
            ><q-badge
              :color="props.row.priority === 'urgent' ? 'red-1' : 'cyan-1'"
              :text-color="props.row.priority === 'urgent' ? 'red-8' : 'cyan-9'"
              >{{ labels[props.row.priority] }}</q-badge
            ></q-td
          ></template
        >
        <template #body-cell-stock="props"
          ><q-td :props="props"
            ><span
              :class="
                props.row.low_stock
                  ? 'text-orange-9 text-weight-bold'
                  : 'text-primary'
              "
              >{{ props.row.stock }} {{ props.row.unit }}</span
            ><div v-if="props.row.low_stock" class="text-caption text-orange-8"
              >Stock bajo</div
            ></q-td
          ></template
        >
        <template #body-cell-actions="props"
          ><q-td :props="props"
            ><div class="row no-wrap q-gutter-xs">
              <q-btn
                flat
                round
                dense
                icon="visibility"
                color="grey-7"
                aria-label="Ver detalle"
                @click="showDetail(props.row)"
              />
              <q-btn
                v-if="
                  (clinical &&
                    ['pacientes', 'medicamentos'].includes(module)) ||
                  module === 'usuarios' ||
                  (clinical &&
                    module === 'atenciones' &&
                    props.row.status === 'in_progress')
                "
                flat
                round
                dense
                icon="edit_note"
                color="primary"
                aria-label="Editar registro"
                @click="editRecord(props.row)"
              />
              <q-btn
                v-if="
                  module === 'solicitudes' &&
                  props.row.status === 'pending' &&
                  clinical
                "
                flat
                dense
                no-caps
                color="primary"
                label="Atender"
                @click="start(props.row)"
              />
              <q-btn
                v-if="
                  module === 'solicitudes' && props.row.status === 'pending'
                "
                flat
                round
                dense
                icon="close"
                color="grey-7"
                aria-label="Cancelar solicitud"
                @click="askCancel(props.row)"
              />
              <q-btn
                v-if="module === 'medicamentos'"
                flat
                dense
                no-caps
                color="primary"
                icon="add"
                label="Ingreso"
                @click="stockEntry(props.row)"
              />
              <q-btn
                v-if="
                  module === 'atenciones' &&
                  clinical &&
                  props.row.status === 'in_progress'
                "
                flat
                round
                dense
                icon="medication"
                color="primary"
                aria-label="Entregar medicamento"
                @click="delivery(props.row)"
              />
              <q-btn
                v-if="
                  module === 'atenciones' &&
                  clinical &&
                  props.row.status === 'in_progress'
                "
                flat
                round
                dense
                icon="moving"
                color="primary"
                aria-label="Derivar paciente"
                @click="referral(props.row)"
              />
              <q-btn
                v-if="
                  module === 'derivaciones' &&
                  clinical &&
                  props.row.status === 'open'
                "
                flat
                dense
                no-caps
                color="primary"
                label="Seguimiento"
                @click="followUp(props.row)"
              /> </div></q-td
        ></template> </q-table
    ></section>
    <section
      v-if="module === 'reportes' && report?.deliveries.length"
      class="panel q-mt-lg"
      ><div class="panel-heading"
        ><h2>Consumo de medicamentos en las atenciones del período</h2></div
      ><q-list separator
        ><q-item v-for="item in report.deliveries" :key="item.name"
          ><q-item-section>{{ item.name }}</q-item-section
          ><q-item-section side
            >{{ item.quantity }} {{ item.unit }}</q-item-section
          ></q-item
        ></q-list
      ></section
    >
    <q-dialog v-model="formOpen" persistent
      ><q-card class="dialog-card"
        ><div class="dialog-heading"
          ><h2>{{ formTitle }}</h2
          ><q-btn
            flat
            round
            dense
            icon="close"
            aria-label="Cerrar formulario"
            v-close-popup /></div
        ><q-card-section class="q-pa-lg"
          ><RecordForm
            :key="formKey"
            :fields="fields"
            :initial="initial"
            :save="saveFunction"
            @cancel="formOpen = false"
            @saved="saved" /></q-card-section></q-card
    ></q-dialog>
    <q-dialog v-model="detailOpen"
      ><q-card class="dialog-card"
        ><div class="dialog-heading"
          ><div
            ><span class="eyebrow">TÓPICO · REGISTRO DIGITAL</span
            ><h2 class="q-mt-sm">{{ detailTitle }}</h2></div
          ><div
            ><q-btn
              flat
              round
              icon="print"
              aria-label="Imprimir registro"
              @click="printRecord" /><q-btn
              flat
              round
              icon="close"
              aria-label="Cerrar detalle"
              v-close-popup /></div></div
        ><q-card-section class="q-pa-lg"
          ><div class="detail-grid"
            ><div
              v-for="item in detailFields"
              :key="item.label"
              :class="{ 'full-field': item.wide }"
              ><span class="detail-label">{{ item.label }}</span
              ><div class="detail-value">{{ item.value ?? "—" }}</div></div
            ></div
          >
          <div v-if="detail?.patient" class="detail-section"
            ><h3>Antecedentes del paciente</h3
            ><p
              ><strong>Alergias:</strong>
              {{ detail.patient.allergies || "Sin información registrada" }}</p
            ><p
              ><strong>Antecedentes:</strong>
              {{ detail.patient.background || "Sin información registrada" }}</p
            ><p
              ><strong>Contacto de emergencia:</strong>
              {{ detail.patient.emergency_contact || "—" }} ·
              {{ detail.patient.emergency_phone || "—" }}</p
            ></div
          >
          <div v-if="detail?.deliveries?.length" class="detail-section"
            ><h3>Medicamentos entregados</h3
            ><div
              v-for="item in detail.deliveries"
              :key="item.id"
              class="q-mb-md"
              ><strong
                >{{ item.name }} · {{ item.quantity }} {{ item.unit }}</strong
              ><p>Lote {{ item.lot }} · {{ item.instructions }}</p></div
            ></div
          >
          <div v-if="detail?.referrals?.length" class="detail-section"
            ><h3>Derivaciones</h3
            ><div
              v-for="item in detail.referrals"
              :key="item.id"
              class="q-mb-md"
              ><strong
                >{{ item.destination }} · {{ labels[item.status] }}</strong
              ><p>{{ item.reason }}</p
              ><p v-if="item.follow_up"
                >Seguimiento: {{ item.follow_up }}</p
              ></div
            ></div
          >
          <div v-if="detail?.consultations" class="detail-section"
            ><h3>Historial de atenciones</h3
            ><p v-if="!detail.consultations.length" class="text-grey-6"
              >Sin atenciones registradas.</p
            ><q-list separator
              ><q-item
                v-for="item in detail.consultations"
                :key="item.id"
                clickable
                @click="openConsultation(item.id)"
                ><q-item-section
                  ><q-item-label>{{ item.reason }}</q-item-label
                  ><q-item-label caption>{{
                    date(item.created_at)
                  }}</q-item-label></q-item-section
                ><q-item-section side
                  >{{ labels[item.status] }}
                  <q-icon
                    name="chevron_right" /></q-item-section></q-item></q-list
          ></div>
          <div v-if="detail?.batches" class="detail-section"
            ><h3>Lotes registrados</h3
            ><q-list separator
              ><q-item v-for="item in detail.batches" :key="item.id"
                ><q-item-section
                  ><q-item-label>{{ item.lot }}</q-item-label
                  ><q-item-label caption
                    >Vence: {{ date(item.expires_at) }}</q-item-label
                  ></q-item-section
                ><q-item-section side
                  >{{ item.quantity }} unidades</q-item-section
                ></q-item
              ></q-list
            ><p class="text-caption text-grey-7 q-mt-md"
              >El stock disponible excluye lotes vencidos o que vencen hoy.</p
            ></div
          >
          <div v-if="detail?.movements" class="detail-section"
            ><q-list separator
              ><q-item v-for="item in detail.movements" :key="item.id"
                ><q-item-section
                  ><q-item-label
                    >{{ item.name }} · {{ labels[item.type] }} ·
                    {{ item.quantity }}</q-item-label
                  ><q-item-label caption
                    >Lote {{ item.lot }} · {{ item.responsible }} ·
                    {{ date(item.created_at) }}</q-item-label
                  ><q-item-label caption>{{
                    item.instructions
                  }}</q-item-label></q-item-section
                ></q-item
              ></q-list
            ><p v-if="!detail.movements.length"
              >Sin movimientos registrados.</p
            ></div
          >
        </q-card-section></q-card
      ></q-dialog
    >
    <q-dialog v-model="cancelOpen"
      ><q-card style="width: 420px; max-width: 95vw"
        ><q-card-section
          ><h2>Cancelar solicitud</h2
          ><p class="q-mt-md"
            >Se cancelará la solicitud de {{ cancelRow?.patient_name }}.</p
          ></q-card-section
        ><q-card-actions align="right"
          ><q-btn flat no-caps label="Volver" v-close-popup /><q-btn
            color="negative"
            unelevated
            no-caps
            label="Cancelar solicitud"
            @click="cancel"
            :loading="actionBusy" /></q-card-actions></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuasar, type QTableColumn } from "quasar";
import RecordForm, { type Field } from "@/components/RecordForm.vue";
import {
  api,
  session,
  labels,
  roles,
  statusColors,
  date,
  type Row
} from "@/services/api";
const route = useRoute();
const router = useRouter();
const $q = useQuasar();
const module = computed(() => route.meta.module as string);
const clinical = computed(() =>
  ["coordinator", "staff"].includes(session.user?.role)
);
const rows = ref<Row[]>([]);
const loading = ref(false);
const error = ref("");
const search = ref("");
const status = ref<string | null>(null);
const report = ref<Row | null>(null);
const localToday = new Date().toLocaleDateString("en-CA");
const from = ref(localToday.slice(0, 8) + "01");
const to = ref(localToday);
const patientType = ref<string | null>(null);
const formOpen = ref(false);
const formTitle = ref("");
const fields = ref<Field[]>([]);
const initial = ref<Row>({});
const formKey = ref(0);
let saveFunction: (values: Row) => Promise<void> = async () => {};
const detailOpen = ref(false);
const detail = ref<Row | null>(null);
const detailTitle = ref("");
const detailFields = ref<{ label: string; value: any; wide?: boolean }[]>([]);
const cancelOpen = ref(false);
const cancelRow = ref<Row | null>(null);
const actionBusy = ref(false);
const endpoints: Record<string, string> = {
  pacientes: "patients",
  solicitudes: "requests",
  atenciones: "consultations",
  medicamentos: "medicines",
  derivaciones: "referrals",
  usuarios: "users",
  reportes: "reports"
};
const descriptions: Record<string, string> = {
  pacientes: "Datos personales, contactos e historial de cada paciente.",
  solicitudes:
    "Organiza las solicitudes y acompaña cada caso desde el primer aviso.",
  atenciones:
    "Registra los cuidados, signos vitales e indicaciones del paciente.",
  medicamentos: "Control de existencias, lotes, vencimientos e ingresos.",
  derivaciones: "Conecta la atención con el centro de salud y su seguimiento.",
  reportes: "Información para supervisar y mejorar la atención.",
  usuarios: "Administra los accesos y permisos del equipo."
};
const createLabels: Record<string, string> = {
  pacientes: "Registrar paciente",
  solicitudes: "Solicitar atención",
  atenciones: "Nueva atención",
  medicamentos: "Nuevo medicamento",
  derivaciones: "Nueva derivación",
  usuarios: "Crear usuario"
};
const canCreate = computed(
  () =>
    module.value !== "reportes" &&
    (clinical.value || module.value === "solicitudes")
);
const options = (items: Record<string, string>) =>
  Object.entries(items).map(([value, label]) => ({ value, label }));
const typeOptions = options({
  student: "Alumno",
  teacher: "Docente",
  staff: "Personal"
});
const priorityOptions = options({ normal: "Normal", urgent: "Urgente" });
const statusOptions = computed(() =>
  options(
    module.value === "solicitudes"
      ? {
          pending: "Pendiente",
          in_progress: "En atención",
          completed: "Finalizada",
          cancelled: "Cancelada"
        }
      : module.value === "derivaciones"
        ? { open: "Pendiente", closed: "Cerrada" }
        : { in_progress: "En atención", completed: "Finalizada" }
  )
);
const filteredRows = computed(() =>
  status.value
    ? rows.value.filter(row => row.status === status.value)
    : rows.value
);
const col = (
  name: string,
  label: string,
  format?: (value: any, row: Row) => any
): QTableColumn => ({
  name,
  label,
  field: name,
  align: "left",
  sortable: true,
  ...(format ? { format } : {})
});
const columns = computed<QTableColumn[]>(() => {
  const sets: Record<string, QTableColumn[]> = {
    pacientes: [
      col("name", "Paciente"),
      col("document", "Documento"),
      col("type", "Tipo", v => labels[v]),
      col("group", "Grado / área"),
      col("phone", "Teléfono")
    ],
    solicitudes: [
      col("patient_name", "Paciente"),
      col("reason", "Motivo"),
      col("priority", "Prioridad"),
      col("created_at", "Fecha", date),
      col("status", "Estado")
    ],
    atenciones: [
      col("patient_name", "Paciente"),
      col("reason", "Motivo"),
      col("attended_by_name", "Responsable"),
      col("created_at", "Fecha", date),
      col("status", "Estado")
    ],
    medicamentos: [
      col("name", "Medicamento"),
      col("presentation", "Presentación"),
      col("stock", "Disponible"),
      col("minimum_stock", "Stock mínimo")
    ],
    derivaciones: [
      col("patient_name", "Paciente"),
      col("destination", "Destino"),
      col("priority", "Prioridad"),
      col("created_at", "Fecha", date),
      col("status", "Estado")
    ],
    usuarios: [
      col("name", "Usuario"),
      col("email", "Correo electrónico"),
      col("role", "Rol", v => roles[v]),
      col("active", "Acceso", v => (v ? "Activo" : "Desactivado"))
    ],
    reportes: [
      col("patient_name", "Paciente"),
      col("patient_type", "Tipo", v => labels[v]),
      col("reason", "Motivo"),
      col("created_at", "Fecha", date),
      col("status", "Estado")
    ]
  };
  return [
    ...(sets[module.value] || []),
    { name: "actions", label: "Acciones", field: "id", align: "right" }
  ];
});
let loadVersion = 0;
async function load() {
  const version = ++loadVersion;
  loading.value = true;
  error.value = "";
  const current = module.value;
  try {
    const query =
      current === "reportes"
        ? `?${new URLSearchParams({ from: from.value, to: to.value, ...(patientType.value ? { type: patientType.value } : {}) })}`
        : "";
    const result = await api(`/${endpoints[current]}${query}`);
    if (version !== loadVersion) return;
    if (current === "reportes") {
      report.value = result;
      rows.value = result.rows;
    } else rows.value = result;
  } catch (e) {
    if (version === loadVersion) error.value = (e as Error).message;
  } finally {
    if (version === loadVersion) loading.value = false;
  }
}
function openForm(
  title: string,
  formFields: Field[],
  data: Row,
  save: (values: Row) => Promise<void>
) {
  formTitle.value = title;
  fields.value = formFields;
  initial.value = data;
  saveFunction = save;
  formKey.value++;
  formOpen.value = true;
}
function entityForm(
  title: string,
  formFields: Field[],
  data: Row,
  endpoint: string,
  id?: number
) {
  openForm(title, formFields, data, async values => {
    await api(`/${endpoint}${id ? "/" + id : ""}`, id ? "PUT" : "POST", values);
  });
}
function field(key: string, label: string, extra: Partial<Field> = {}): Field {
  return { key, label, ...extra };
}
const patientFields: Field[] = [
  field("name", "Nombre completo", { required: true }),
  field("document", "Documento de identidad", { required: true }),
  field("type", "Tipo de paciente", { required: true, options: typeOptions }),
  field("birth_date", "Fecha de nacimiento", { type: "date" }),
  field("group", "Grado / sección / área"),
  field("phone", "Teléfono"),
  field("emergency_contact", "Contacto de emergencia"),
  field("emergency_phone", "Teléfono de emergencia"),
  field("allergies", "Alergias conocidas", { type: "textarea", wide: true }),
  field("background", "Antecedentes relevantes", {
    type: "textarea",
    wide: true
  })
];
const medicineFields: Field[] = [
  field("name", "Nombre del medicamento", { required: true }),
  field("presentation", "Presentación / concentración", { required: true }),
  field("unit", "Unidad de entrega", {
    required: true,
    hint: "Ej.: tableta, frasco, sobre"
  }),
  field("minimum_stock", "Stock mínimo", {
    required: true,
    type: "number",
    min: 0
  })
];
async function patientsOptions() {
  const list: Row[] = await api("/patients");
  return list.map(p => ({
    label: `${p.name}${p.document ? " · " + p.document : ""}${p.group ? " · " + p.group : ""}`,
    value: p.id
  }));
}
async function consultationFields(): Promise<Field[]> {
  return [
    field("patient_id", "Paciente", {
      required: true,
      options: await patientsOptions(),
      wide: true
    }),
    field("reason", "Motivo de atención", {
      required: true,
      type: "textarea",
      wide: true
    }),
    field("symptoms", "Síntomas", { type: "textarea", wide: true }),
    field("temperature", "Temperatura (°C)", {
      type: "number",
      min: 20,
      max: 50,
      step: "0.1"
    }),
    field("heart_rate", "Frecuencia cardíaca (lpm)", {
      type: "number",
      min: 10,
      max: 300
    }),
    field("systolic", "Presión sistólica (mmHg)", {
      type: "number",
      min: 30,
      max: 300
    }),
    field("diastolic", "Presión diastólica (mmHg)", {
      type: "number",
      min: 10,
      max: 200
    }),
    field("saturation", "Saturación (%)", { type: "number", min: 1, max: 100 }),
    field("weight", "Peso (kg)", {
      type: "number",
      min: 0.1,
      max: 500,
      step: "0.01"
    }),
    field("height", "Talla (cm)", {
      type: "number",
      min: 10,
      max: 250,
      step: "0.01"
    }),
    field("observations", "Observaciones", { type: "textarea", wide: true }),
    field("care", "Cuidados realizados", { type: "textarea", wide: true }),
    field("instructions", "Indicaciones para el paciente", {
      type: "textarea",
      wide: true
    }),
    field("status", "Estado de la atención", {
      required: true,
      options: options({ in_progress: "En atención", completed: "Finalizada" })
    })
  ];
}
async function userFields(): Promise<Field[]> {
  return [
    field("name", "Nombre completo", { required: true }),
    field("email", "Correo electrónico", { required: true, type: "email" }),
    field("role", "Rol", { required: true, options: options(roles) }),
    field("active", "Acceso", {
      required: true,
      options: [
        { label: "Activo", value: true },
        { label: "Desactivado", value: false }
      ]
    }),
    field("password", "Contraseña", {
      type: "password",
      hint: "Mínimo 10 caracteres. Al editar, vacío conserva la actual.",
      wide: true
    }),
    field("patient_id", "Paciente vinculado (obligatorio para rol Paciente)", {
      options: await patientsOptions(),
      wide: true
    })
  ];
}
async function safely(action: () => Promise<void>) {
  try {
    await action();
  } catch (e) {
    $q.notify({ type: "negative", message: (e as Error).message });
  }
}
async function createRecord() {
  await safely(async () => {
    switch (module.value) {
      case "pacientes":
        entityForm(
          "Registrar paciente",
          patientFields,
          { type: "student" },
          "patients"
        );
        break;
      case "solicitudes": {
        const choices = await patientsOptions();
        if (!choices.length)
          throw new Error(
            session.user?.role === "patient"
              ? "Tu cuenta no tiene un paciente vinculado. Contacta al coordinador."
              : "Primero registra el paciente desde el módulo Pacientes."
          );
        entityForm(
          "Solicitar atención",
          [
            field("patient_id", "Paciente", {
              required: true,
              options: choices,
              wide: true
            }),
            field("reason", "Motivo de la solicitud", {
              required: true,
              type: "textarea",
              wide: true
            }),
            field("priority", "Prioridad", {
              required: true,
              options: priorityOptions
            })
          ],
          {
            priority: "normal",
            ...(choices.length === 1 ? { patient_id: choices[0]!.value } : {})
          },
          "requests"
        );
        break;
      }
      case "atenciones":
        entityForm(
          "Nueva atención",
          await consultationFields(),
          { status: "in_progress" },
          "consultations"
        );
        break;
      case "medicamentos":
        entityForm(
          "Nuevo medicamento",
          medicineFields,
          { minimum_stock: 10, unit: "tableta" },
          "medicines"
        );
        break;
      case "derivaciones":
        await referral();
        break;
      case "usuarios": {
        const f = await userFields();
        f.find(x => x.key === "password")!.required = true;
        entityForm(
          "Crear usuario",
          f,
          { role: "staff", active: true },
          "users"
        );
        break;
      }
    }
  });
}
async function editRecord(row: Row) {
  await safely(async () => {
    if (module.value === "pacientes")
      entityForm(
        "Editar paciente",
        patientFields,
        { ...row },
        "patients",
        row.id
      );
    if (module.value === "medicamentos")
      entityForm(
        "Editar medicamento",
        medicineFields,
        { ...row },
        "medicines",
        row.id
      );
    if (module.value === "atenciones")
      entityForm(
        "Registrar cuidados y signos vitales",
        await consultationFields(),
        { ...row },
        "consultations",
        row.id
      );
    if (module.value === "usuarios")
      entityForm(
        "Editar usuario",
        await userFields(),
        { ...row, active: !!row.active, password: "" },
        "users",
        row.id
      );
  });
}
async function saved() {
  formOpen.value = false;
  $q.notify({ type: "positive", message: "Registro guardado correctamente." });
  await load();
}
async function start(row: Row) {
  await safely(async () => {
    const result = await api(`/requests/${row.id}/start`, "POST");
    await router.push("/atenciones");
    const record = await api(`/consultations/${result.id}`);
    entityForm(
      "Registrar atención",
      await consultationFields(),
      record,
      "consultations",
      result.id
    );
  });
}
function askCancel(row: Row) {
  cancelRow.value = row;
  cancelOpen.value = true;
}
async function cancel() {
  actionBusy.value = true;
  await safely(async () => {
    await api(`/requests/${cancelRow.value!.id}/cancel`, "POST");
    cancelOpen.value = false;
    await load();
  });
  actionBusy.value = false;
}
function stockEntry(row: Row) {
  entityForm(
    `Ingreso · ${row.name}`,
    [
      field("lot", "Número de lote", { required: true }),
      field("expires_at", "Fecha de vencimiento", {
        required: true,
        type: "date"
      }),
      field("quantity", `Cantidad (${row.unit})`, {
        required: true,
        type: "number",
        min: 1
      }),
      field("instructions", "Observación del ingreso", {
        type: "textarea",
        wide: true
      })
    ],
    { medicine_id: row.id },
    "stock/entries"
  );
}
async function delivery(row: Row) {
  await safely(async () => {
    const medicines: Row[] = await api("/medicines");
    const batches = medicines.flatMap(m =>
      m.batches
        .filter((b: Row) => b.quantity > 0 && b.expires_at > localToday)
        .map((b: Row) => ({
          label: `${m.name} ${m.presentation} · ${b.lot} · ${b.quantity} ${m.unit} · vence ${date(b.expires_at)}`,
          value: b.id
        }))
    );
    if (!batches.length)
      throw new Error(
        "No hay lotes disponibles. Registra un ingreso de medicamentos."
      );
    entityForm(
      "Entregar medicamento",
      [
        field("batch_id", "Medicamento y lote", {
          required: true,
          options: batches,
          wide: true
        }),
        field("quantity", "Cantidad a entregar", {
          required: true,
          type: "number",
          min: 1
        }),
        field("instructions", "Indicaciones de la entrega", {
          required: true,
          type: "textarea",
          wide: true
        })
      ],
      {},
      `consultations/${row.id}/deliver`
    );
  });
}
async function referral(row?: Row) {
  const list: Row[] = row
    ? [row]
    : (await api("/consultations")).filter(
        (c: Row) => c.status === "in_progress"
      );
  if (!list.length)
    throw new Error(
      "Primero inicia una atención para registrar su derivación."
    );
  entityForm(
    "Derivar a un centro de salud",
    [
      field("consultation_id", "Atención del paciente", {
        required: true,
        options: list.map(c => ({
          label: `#${c.id} · ${c.patient_name} · ${c.reason}`,
          value: c.id
        })),
        wide: true
      }),
      field("destination", "Centro de salud de destino", {
        required: true,
        wide: true
      }),
      field("reason", "Motivo de derivación", {
        required: true,
        type: "textarea",
        wide: true
      }),
      field("priority", "Prioridad", {
        required: true,
        options: priorityOptions
      })
    ],
    { priority: "normal", ...(row ? { consultation_id: row.id } : {}) },
    "referrals"
  );
}
function followUp(row: Row) {
  entityForm(
    "Seguimiento de derivación",
    [
      field("follow_up", "Resultado / seguimiento", {
        required: true,
        type: "textarea",
        wide: true
      }),
      field("status", "Estado", {
        required: true,
        options: options({
          open: "Mantener pendiente",
          closed: "Cerrar derivación"
        })
      })
    ],
    { ...row },
    "referrals",
    row.id
  );
}
const detailMap: Record<string, string> = {
  id: "Número de registro",
  patient_name: "Paciente",
  name: "Nombre",
  document: "Documento",
  type: "Tipo",
  group: "Grado / área",
  birth_date: "Nacimiento",
  phone: "Teléfono",
  emergency_contact: "Contacto de emergencia",
  emergency_phone: "Teléfono de emergencia",
  allergies: "Alergias",
  background: "Antecedentes",
  reason: "Motivo",
  priority: "Prioridad",
  status: "Estado",
  created_at: "Fecha de registro",
  completed_at: "Fecha de cierre",
  requested_by_name: "Solicitado por",
  attended_by_name: "Atendido por",
  symptoms: "Síntomas",
  temperature: "Temperatura (°C)",
  heart_rate: "Frecuencia cardíaca (lpm)",
  systolic: "Presión sistólica (mmHg)",
  diastolic: "Presión diastólica (mmHg)",
  saturation: "Saturación (%)",
  weight: "Peso (kg)",
  height: "Talla (cm)",
  observations: "Observaciones",
  care: "Cuidados realizados",
  instructions: "Indicaciones",
  destination: "Centro de salud",
  responsible: "Responsable",
  follow_up: "Seguimiento",
  closed_at: "Fecha de cierre",
  presentation: "Presentación",
  unit: "Unidad",
  stock: "Stock disponible",
  minimum_stock: "Stock mínimo",
  email: "Correo",
  role: "Rol",
  active: "Acceso"
};
function setDetail(record: Row, title: string) {
  detail.value = record;
  detailTitle.value = title;
  detailFields.value = Object.entries(detailMap)
    .filter(([key]) => record[key] !== undefined)
    .map(([key, label]) => ({
      label,
      value: ["created_at", "completed_at", "birth_date", "closed_at"].includes(
        key
      )
        ? date(record[key])
        : key === "role"
          ? roles[record[key]]
          : key === "active"
            ? record[key]
              ? "Activo"
              : "Desactivado"
            : ["type", "status", "priority"].includes(key)
              ? labels[record[key]]
              : record[key] || "—",
      wide: [
        "reason",
        "symptoms",
        "observations",
        "care",
        "instructions",
        "allergies",
        "background",
        "follow_up"
      ].includes(key)
    }));
  detailOpen.value = true;
}
async function openConsultation(id: number) {
  await safely(async () => {
    setDetail(await api(`/consultations/${id}`), `Atención #${id}`);
  });
}
async function showDetail(row: Row) {
  await safely(async () => {
    if (["atenciones", "reportes"].includes(module.value)) {
      await openConsultation(row.id);
      return;
    }
    if (module.value === "pacientes") {
      const result = await api(`/patients/${row.id}/history`);
      setDetail(
        { ...result.patient, consultations: result.consultations },
        "Ficha del paciente"
      );
      return;
    }
    setDetail(
      row,
      module.value === "derivaciones"
        ? `Derivación #${row.id}`
        : "Detalle del registro"
    );
  });
}
async function showMovements() {
  await safely(async () => {
    setDetail(
      { movements: await api("/stock/movements") },
      "Movimientos de inventario"
    );
  });
}
function printRecord() {
  window.print();
}
function exportCsv() {
  const csvCell = (v: any) =>
    '"' +
    String(v ?? "")
      .replace(/^[=+@\-\t\r]/, "'$&")
      .replaceAll('"', '""') +
    '"';
  const headers = [
    "Paciente",
    "Documento",
    "Tipo",
    "Motivo",
    "Responsable",
    "Fecha",
    "Estado"
  ];
  const values = rows.value.map(r => [
    r.patient_name,
    r.document,
    labels[r.patient_type],
    r.reason,
    r.attended_by_name,
    r.created_at,
    labels[r.status]
  ]);
  const content =
    "\uFEFF" +
    [headers, ...values].map(row => row.map(csvCell).join(";")).join("\r\n");
  const url = URL.createObjectURL(
    new Blob([content], { type: "text/csv;charset=utf-8;" })
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = `atenciones-${from.value}-${to.value}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}
watch(
  module,
  async () => {
    search.value = "";
    status.value = null;
    formOpen.value = false;
    detailOpen.value = false;
    rows.value = [];
    report.value = null;
    await load();
    if (route.query.nueva === "1") await createRecord();
  },
  { immediate: true }
);
</script>
