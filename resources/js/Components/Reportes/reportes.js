// Utilidades compartidas por las vistas de reportes

export const meses = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
]

//años para filtro
export const anios = ['2025', '2026', '2027', '2028', '2029']

// año actual si está en el filtro, si no el primero disponible
export function anioPorDefecto() {
  const actual = new Date().getFullYear().toString()
  return anios.includes(actual) ? actual : anios[0]
}

export function obtenerAnio(fecha) {
  return new Date(fecha).getFullYear().toString()
}

export function obtenerMes(fecha) {
  return new Date(fecha).getMonth() >= 0
    ? meses[new Date(fecha).getMonth()] : ''
}

// fechas
export function formatearFecha(fecha) {
  if (!fecha) return '-'
  if (fecha.includes('T')) {
    fecha = fecha.split('T')[0]
  }
  const match = fecha.match(/\d{4}-\d{2}-\d{2}/)
  if (match) {
    const [year, month, day] = match[0].split('-')
    return `${day}/${month}/${year}`
  }
  return fecha
}

// exporta una tabla HTML como archivo .xls
export function exportarTablaExcel(selector, nombreArchivo) {
  const tabla = document.querySelector(selector)
  if (!tabla) return

  const blob = new Blob(['﻿' + tabla.outerHTML], {
    type: 'application/vnd.ms-excel',
  })

  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `${nombreArchivo}.xls`
  link.click()
}
