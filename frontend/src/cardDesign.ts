export type CardElement = 'callsign' | 'subtitle' | 'location' | 'message'
export type CardDesign = {
  template: 'classic' | 'aurora' | 'sunset'
  background: string
  accent: string
  text: string
  subtitle: string
  location: string
  locator: string
  message: string
  positions: Record<CardElement, { x: number; y: number }>
}
export type StationCard = {
  public_id: string
  title: string
  description: string | null
  callsign: string
  callsign_id?: number
  source: 'editor' | 'upload'
  is_public: boolean
  image_url: string
  created_at: string
  design?: CardDesign | null
}
export type StationCardPage = { data: StationCard[]; meta: { last_page: number } }
export const elementLabels: Record<CardElement, string> = {
  callsign: 'Indicativo',
  subtitle: 'Subtítulo',
  location: 'Ubicación y locator',
  message: 'Mensaje',
}
export function newDesign(template: CardDesign['template'] = 'aurora'): CardDesign {
  const colors = {
    aurora: ['#0b1427', '#22d3ee', '#f8fafc'],
    classic: ['#f5f0df', '#a24b21', '#24352e'],
    sunset: ['#301d48', '#ffb45b', '#fff4e4'],
  }[template]
  return {
    template,
    background: colors[0],
    accent: colors[1],
    text: colors[2],
    subtitle: 'Amateur radio station',
    location: '',
    locator: '',
    message: '73 · Gracias por el contacto',
    positions: {
      callsign: { x: 70, y: 230 },
      subtitle: { x: 75, y: 300 },
      location: { x: 75, y: 425 },
      message: { x: 75, y: 530 },
    },
  }
}
export function cardText(design: CardDesign, callsign: string, key: CardElement) {
  if (key === 'callsign') return callsign || 'TU INDICATIVO'
  if (key === 'location')
    return [design.location, design.locator ? `GRID ${design.locator}` : '']
      .filter(Boolean)
      .join(' · ')
  return design[key] || ''
}
export function drawDesign(
  canvas: HTMLCanvasElement,
  design: CardDesign,
  callsign: string,
  selected?: CardElement,
) {
  canvas.width = 1200
  canvas.height = 750
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Tu navegador no permite crear la vista previa.')
  ctx.fillStyle = design.background
  ctx.fillRect(0, 0, 1200, 750)
  if (design.template === 'aurora') {
    const gradient = ctx.createRadialGradient(1110, 180, 10, 1000, 260, 850)
    gradient.addColorStop(0, design.accent + '88')
    gradient.addColorStop(1, design.background + '00')
    ctx.fillStyle = gradient
    ctx.fillRect(0, 0, 1200, 750)
    ctx.strokeStyle = design.accent + '55'
    ctx.lineWidth = 2
    for (let r = 100; r < 900; r += 100) {
      ctx.beginPath()
      ctx.arc(1100, 190, r, 0, Math.PI * 2)
      ctx.stroke()
    }
  } else if (design.template === 'sunset') {
    const gradient = ctx.createLinearGradient(0, 0, 0, 750)
    gradient.addColorStop(0, design.background)
    gradient.addColorStop(1, design.accent)
    ctx.fillStyle = gradient
    ctx.fillRect(0, 0, 1200, 750)
    ctx.fillStyle = design.accent + 'aa'
    ctx.beginPath()
    ctx.arc(980, 230, 125, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillStyle = design.background + 'aa'
    ctx.beginPath()
    ctx.moveTo(0, 600)
    ctx.lineTo(320, 420)
    ctx.lineTo(610, 590)
    ctx.lineTo(870, 390)
    ctx.lineTo(1200, 560)
    ctx.lineTo(1200, 750)
    ctx.lineTo(0, 750)
    ctx.closePath()
    ctx.fill()
  } else {
    ctx.fillStyle = design.accent
    ctx.fillRect(0, 0, 22, 750)
    ctx.fillRect(1140, 0, 60, 750)
    ctx.strokeStyle = design.accent
    ctx.lineWidth = 2
    ctx.strokeRect(42, 35, 1070, 680)
  }
  ctx.fillStyle = design.accent
  ctx.font = 'bold 22px Arial, sans-serif'
  ctx.fillText('QSL · AMATEUR RADIO', 75, 90)
  const sizes: Record<CardElement, number> = {
    callsign: 104,
    subtitle: 32,
    location: 30,
    message: 30,
  }
  for (const key of Object.keys(elementLabels) as CardElement[]) {
    const point = design.positions[key]
    const content = cardText(design, callsign, key)
    ctx.fillStyle = key === 'callsign' ? design.accent : design.text
    ctx.font = `${key === 'callsign' ? 'bold ' : ''}${sizes[key]}px Arial, sans-serif`
    const width = Math.max(60, 1125 - point.x)
    ctx.fillText(content, point.x, point.y, width)
    if (selected === key) {
      ctx.save()
      ctx.strokeStyle = design.accent
      ctx.lineWidth = 2
      ctx.setLineDash([8, 6])
      ctx.strokeRect(
        point.x - 10,
        point.y - sizes[key] - 5,
        Math.min(ctx.measureText(content).width || 150, width) + 20,
        sizes[key] + 24,
      )
      ctx.restore()
    }
  }
  ctx.fillStyle = design.background + 'cc'
  ctx.fillRect(60, 595, 1060, 105)
  ctx.strokeStyle = design.accent
  ctx.strokeRect(60, 595, 1060, 105)
  ctx.fillStyle = design.text
  ctx.font = 'bold 17px Arial, sans-serif'
  ;['TO RADIO', 'DATE / UTC', 'BAND / MODE', 'RST'].forEach((text, i) =>
    ctx.fillText(text, 80 + i * 263, 625),
  )
  ctx.font = '22px Arial, sans-serif'
  ;[0, 1, 2, 3].forEach((i) => ctx.fillText('_____________', 80 + i * 263, 672))
}
export async function designBlob(design: CardDesign, callsign: string): Promise<Blob> {
  const canvas = document.createElement('canvas')
  drawDesign(canvas, design, callsign)
  return new Promise((resolve, reject) =>
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('No se pudo exportar la tarjeta.'))),
      'image/png',
    ),
  )
}
