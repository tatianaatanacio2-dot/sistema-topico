<template>
  <div class="login-page"
    ><section class="login-story"
      ><div class="brand light"
        ><div class="brand-icon"><q-icon name="add" size="28px" /></div
        ><div>Tópico<span>Salud y cuidado</span></div></div
      ><div
        ><span class="eyebrow">UN ESPACIO PARA CUIDAR</span
        ><h1>El bienestar<br />empieza con<br />una buena atención.</h1
        ><p
          >Pacientes, atenciones y medicamentos.<br />Todo conectado en un solo
          lugar.</p
        ><div class="story-mark"
          ><q-icon name="health_and_safety" size="100px" /></div></div
      ><small>Sistema de gestión del tópico</small></section
    ><section class="login-panel"
      ><div class="login-box"
        ><span class="eyebrow text-primary">BIENVENIDO A TÓPICO</span
        ><h2>Inicia sesión</h2
        ><p class="text-grey-7 q-mb-xl"
          >Ingresa tus credenciales para acceder a tu espacio.</p
        ><q-form @submit="login"
          ><q-input
            outlined
            v-model="email"
            type="email"
            label="Correo electrónico"
            autocomplete="username"
            :rules="[v => !!v || 'Ingresa tu correo']" /><q-input
            outlined
            v-model="password"
            :type="visible ? 'text' : 'password'"
            label="Contraseña"
            autocomplete="current-password"
            :rules="[v => !!v || 'Ingresa tu contraseña']"
            ><template #append
              ><q-icon
                :name="visible ? 'visibility_off' : 'visibility'"
                class="cursor-pointer"
                @click="visible = !visible" /></template></q-input
          ><q-banner v-if="error" class="bg-red-1 text-red-9 q-mb-md" rounded>{{
            error
          }}</q-banner
          ><q-btn
            type="submit"
            color="primary"
            unelevated
            no-caps
            size="lg"
            class="full-width q-mt-md"
            label="Ingresar al sistema"
            :loading="busy" /></q-form
        ><p class="login-help"
          >Si necesitas acceso, comunícate con el coordinador del tópico.</p
        ></div
      ></section
    ></div
  >
</template>
<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { api, session } from "@/services/api";
const router = useRouter();
const email = ref("");
const password = ref("");
const visible = ref(false);
const busy = ref(false);
const error = ref("");
async function login() {
  busy.value = true;
  error.value = "";
  try {
    session.user = await api("/login", "POST", {
      email: email.value,
      password: password.value
    });
    await router.push("/");
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
