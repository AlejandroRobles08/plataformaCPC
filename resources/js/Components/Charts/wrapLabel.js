// Parte un nombre largo en renglones de máximo `max` caracteres (Chart.js acepta arreglos como etiqueta multilínea)
export function wrapLabel(text, max = 28) {
  const words = String(text ?? '').replace(/\s+/g, ' ').trim().split(' ')
  const lines = []
  let line = ''

  for (const word of words) {
    if (line && (line + ' ' + word).length > max) {
      lines.push(line)
      line = word
    } else {
      line = line ? line + ' ' + word : word
    }
  }
  if (line) lines.push(line)

  return lines
}
