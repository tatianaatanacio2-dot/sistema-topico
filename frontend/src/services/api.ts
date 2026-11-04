import { reactive } from "vue";
export type Row = Record<string, any>;
export const session = reactive<{ user: Row | null; ready: boolean }>({
  user: null,
  ready: false
});
let csrf = "";
export async function api<T = any>(
  path: string,
  method = "GET",
  data?: unknown
): Promise<T> {
  if (method !== "GET" && !csrf) {
    const response = await fetch("/api/csrf", {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });
    if (!response.ok) throw new Error("No se pudo conectar con el servidor.");
    csrf = (await response.json()).token;
  }
  const response = await fetch(`/api${path}`, {
    method,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrf
    },
    ...(data ? { body: JSON.stringify(data) } : {})
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401) session.user = null;
    if (response.status === 419) csrf = "";
    throw new Error(
      body.errors
        ? Object.values(body.errors).flat().join(" ")
        : body.message || "No se pudo completar la operación."
    );
  }
  if (path === "/login" || path === "/logout") csrf = "";
  return body;
}
export async function loadSession() {
  try {
    session.user = await api("/me");
  } catch {
    session.user = null;
  }
  session.ready = true;
}
export const roles: Record<string, string> = {
  coordinator: "Coordinador",
  staff: "Encargado de tópico",
  teacher: "Docente",
  patient: "Paciente"
};
export const labels: Record<string, string> = {
  pending: "Pendiente",
  in_progress: "En atención",
  completed: "Finalizada",
  cancelled: "Cancelada",
  urgent: "Urgente",
  normal: "Normal",
  student: "Alumno",
  teacher: "Docente",
  staff: "Personal",
  open: "Pendiente",
  closed: "Cerrada",
  entry: "Ingreso",
  delivery: "Entrega"
};
export const statusColors: Record<string, string> = {
  pending: "orange-8",
  in_progress: "blue-8",
  completed: "cyan-8",
  cancelled: "grey-7",
  urgent: "red-7",
  normal: "cyan-8",
  open: "orange-8",
  closed: "cyan-8"
};
export function date(value: string | null | undefined) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("es-CO", {
    dateStyle: "medium",
    ...(value.includes("T") || value.includes(" ")
      ? { timeStyle: "short" as const }
      : {})
  }).format(
    new Date(
      value.length === 10 ? `${value}T12:00:00` : value.replace(" ", "T")
    )
  );
}
