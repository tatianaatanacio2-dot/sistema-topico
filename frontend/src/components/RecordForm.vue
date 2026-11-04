<template>
  <q-form @submit="submit"
    ><div class="form-grid"
      ><template v-for="field in fields" :key="field.key">
        <q-select
          v-if="field.options"
          v-model="values[field.key]"
          :label="field.label"
          :options="field.options"
          emit-value
          map-options
          outlined
          :class="{ 'full-field': field.wide }"
          :rules="field.required ? [required] : []"
          :clearable="!field.required"
        />
        <q-input
          v-else
          v-model="values[field.key]"
          :label="field.label"
          :type="field.type || 'text'"
          outlined
          :class="{ 'full-field': field.wide }"
          :rules="field.required ? [required] : []"
          :min="field.min"
          :max="field.max"
          :step="field.step"
          :hint="field.hint"
        /> </template></div
    ><q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mt-md">{{
      error
    }}</q-banner
    ><div class="row justify-end q-gutter-sm q-mt-lg"
      ><q-btn
        flat
        label="Cancelar"
        no-caps
        @click="$emit('cancel')"
        :disable="busy" /><q-btn
        color="primary"
        unelevated
        label="Guardar registro"
        no-caps
        type="submit"
        :loading="busy" /></div
  ></q-form>
</template>
<script setup lang="ts">
import { reactive, ref } from "vue";
import type { Row } from "@/services/api";
export interface Field {
  key: string;
  label: string;
  type?: "text" | "textarea" | "number" | "date" | "email" | "password";
  required?: boolean;
  wide?: boolean;
  options?: { label: string; value: any }[];
  min?: number;
  max?: number;
  step?: string;
  hint?: string;
}
const props = defineProps<{
  fields: Field[];
  initial?: Row;
  save: (values: Row) => Promise<void>;
}>();
const emit = defineEmits(["saved", "cancel"]);
const values = reactive<Row>({ ...props.initial });
const busy = ref(false);
const error = ref("");
const required = (value: unknown) =>
  (value !== null && value !== undefined && value !== "") ||
  "Este campo es obligatorio";
async function submit() {
  busy.value = true;
  error.value = "";
  try {
    await props.save(values);
    emit("saved");
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
