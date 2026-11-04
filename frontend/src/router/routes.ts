import type { RouteRecordRaw } from "vue-router";
const team = ["coordinator", "staff"];
const routes: RouteRecordRaw[] = [
  {
    path: "/ingresar",
    component: () => import("@/pages/LoginPage.vue"),
    meta: { public: true, title: "Ingresar" }
  },
  {
    path: "/",
    component: () => import("@/layouts/MainLayout.vue"),
    children: [
      {
        path: "",
        component: () => import("@/pages/IndexPage.vue"),
        meta: { title: "Resumen general" }
      },
      ...[
        ["pacientes", "Pacientes", team],
        [
          "solicitudes",
          "Solicitudes de atención",
          [...team, "teacher", "patient"]
        ],
        ["atenciones", "Atenciones", [...team, "patient"]],
        ["medicamentos", "Medicamentos", team],
        ["derivaciones", "Derivaciones", [...team, "patient"]],
        ["reportes", "Reportes", ["coordinator"]],
        ["usuarios", "Usuarios y permisos", ["coordinator"]]
      ].map(([path, title, roles]) => ({
        path: path as string,
        component: () => import("@/pages/WorkspacePage.vue"),
        meta: { title, roles, module: path }
      }))
    ]
  },
  {
    path: "/:catchAll(.*)*",
    component: () => import("@/pages/ErrorNotFound.vue")
  }
];
export default routes;
