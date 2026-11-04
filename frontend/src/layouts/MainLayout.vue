<template>
  <q-layout view="lHh Lpr lFf"
    ><q-header class="topbar"
      ><q-toolbar class="q-px-lg"
        ><q-btn
          flat
          round
          icon="menu"
          color="grey-8"
          aria-label="Abrir menú"
          @click="drawer = !drawer"
        /><q-toolbar-title class="text-body2 text-grey-7"
          >Gestión del bienestar
          <span class="gt-sm"> / {{ route.meta.title }}</span></q-toolbar-title
        ><span class="today gt-sm">{{ today }}</span
        ><q-separator vertical inset class="q-mx-md" /><q-avatar
          size="36px"
          class="user-avatar"
          >{{ session.user?.name?.slice(0, 2).toUpperCase() }}</q-avatar
        ><div class="q-ml-sm gt-xs"
          ><strong>{{ session.user?.name }}</strong
          ><small class="block text-grey-6">{{
            roles[session.user?.role]
          }}</small></div
        ></q-toolbar
      ></q-header
    >
    <q-drawer v-model="drawer" show-if-above :width="252" class="sidebar"
      ><div class="brand"
        ><div class="brand-icon"><q-icon name="add" size="28px" /></div
        ><div>Tópico<span>Salud y cuidado</span></div></div
      ><div class="nav-label">ESPACIO DE TRABAJO</div
      ><q-list class="nav-list"
        ><q-item
          v-for="item in navigation"
          :key="item.to"
          clickable
          :to="item.to"
          exact
          active-class="nav-active"
          ><q-item-section avatar
            ><q-icon :name="item.icon" size="21px" /></q-item-section
          ><q-item-section>{{ item.label }}</q-item-section></q-item
        ></q-list
      ><div class="sidebar-bottom"
        ><div class="care-note"
          ><q-icon name="health_and_safety" size="25px" /><strong
            >Cada atención cuenta</strong
          ><p>La información a tiempo hace la diferencia.</p></div
        ><q-btn
          flat
          no-caps
          icon="logout"
          label="Cerrar sesión"
          class="full-width"
          @click="logout" /></div></q-drawer
    ><q-page-container><router-view /></q-page-container
  ></q-layout>
</template>
<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, session, roles } from "@/services/api";
const drawer = ref(false);
const route = useRoute();
const router = useRouter();
const today = new Intl.DateTimeFormat("es-CO", {
  day: "numeric",
  month: "long",
  year: "numeric"
}).format(new Date());
const navigation = computed(() =>
  [
    {
      to: "/",
      label: "Resumen general",
      icon: "space_dashboard",
      roles: ["coordinator", "staff", "teacher", "patient"]
    },
    {
      to: "/pacientes",
      label: "Pacientes",
      icon: "people_outline",
      roles: ["coordinator", "staff"]
    },
    {
      to: "/solicitudes",
      label: "Solicitudes de atención",
      icon: "inbox",
      roles: ["coordinator", "staff", "teacher", "patient"]
    },
    {
      to: "/atenciones",
      label: session.user?.role === "patient" ? "Mis atenciones" : "Atenciones",
      icon: "medical_services",
      roles: ["coordinator", "staff", "patient"]
    },
    {
      to: "/medicamentos",
      label: "Medicamentos",
      icon: "medication",
      roles: ["coordinator", "staff"]
    },
    {
      to: "/derivaciones",
      label: "Derivaciones",
      icon: "moving",
      roles: ["coordinator", "staff", "patient"]
    },
    {
      to: "/reportes",
      label: "Reportes",
      icon: "bar_chart",
      roles: ["coordinator"]
    },
    {
      to: "/usuarios",
      label: "Usuarios y permisos",
      icon: "manage_accounts",
      roles: ["coordinator"]
    }
  ].filter(item => item.roles.includes(session.user?.role))
);
async function logout() {
  await api("/logout", "POST");
  session.user = null;
  await router.push("/ingresar");
}
</script>
