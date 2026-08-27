// Побудова конфігурації Chart.js для журналу здоров'я. Спільна для сторінки
// «Здоров'я» і друкованого звіту — один код, однаковий вигляд.
import { Chart } from 'chart.js/auto'
import annotationPlugin from 'chartjs-plugin-annotation'
import 'chartjs-adapter-date-fns'
import { uk } from 'date-fns/locale'
import { healthType } from '../api'

Chart.register(annotationPlugin)

export { Chart }

export const PRESSURE_COLORS = { sbp: '#b91c1c', dbp: '#1d4ed8', pulse: '#2f6b4f' }
export const HOME_NORM = { sbp: 135, dbp: 85 }

/** Стиль накладення за замовчуванням для типу події. */
export const defaultStyle = (ht) => (ht.severity ? 'point' : ht.kg ? 'line' : 'marker')

/** Подія без часу ставиться на полудень (для точок) або розтягується смугою на день (для маркерів). */
export const eventMoment = (e) => new Date(`${e.date}T${e.time ?? '12:00'}:00`)

const normLine = (value, label, color) => ({
  type: 'line',
  yMin: value,
  yMax: value,
  borderColor: color,
  borderWidth: 1.5,
  borderDash: [6, 4],
  label: {
    display: true,
    content: label,
    position: 'end',
    font: { size: 10 },
    backgroundColor: color,
  },
})

function baseOptions({ annotations, axes, t, locale, animate, xRange, kgRange }) {
  const scales = {
    x: {
      type: 'time',
      adapters: { date: { locale: locale === 'uk' ? uk : undefined } },
      time: {
        tooltipFormat: 'dd.MM HH:mm',
        minUnit: 'hour',
        displayFormats: { day: 'dd.MM', hour: 'dd.MM HH:mm' },
      },
      ticks: { maxRotation: 60, autoSkip: true, maxTicksLimit: 12 },
      // Звіт фіксує вісь на межах місяця, щоб графіки різних місяців мали один масштаб
      ...(xRange ? { min: xRange.min, max: xRange.max } : {}),
    },
    // Явно, інакше датасети без yAxisID чіпляються до першої знайденої осі (severity)
    y: { position: 'left', display: axes.y !== false },
  }
  if (axes.severity) {
    scales.severity = {
      position: 'right',
      min: 0,
      max: 5,
      grid: { drawOnChartArea: false },
      title: { display: true, text: t('health.severityAxis') },
    }
  }
  if (axes.kg) {
    scales.kg = {
      position: 'right',
      grid: { drawOnChartArea: false },
      title: { display: true, text: t('health.kg') },
      // Звіт фіксує спільну шкалу ваги на всі місяці, щоб нахил ліній був порівнюваним
      ...(kgRange ? { min: kgRange.min, max: kgRange.max } : {}),
    }
  }
  if (axes.bpm) {
    scales.bpm = {
      position: 'right',
      grid: { drawOnChartArea: false },
      title: { display: true, text: t('health.bpmAxis') },
    }
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: animate ? undefined : false,
    interaction: { mode: 'nearest', intersect: false },
    plugins: {
      legend: { labels: { usePointStyle: true, boxHeight: 6 } },
      annotation: { annotations },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const base = `${ctx.dataset.label}: ${ctx.parsed.y}`
            if (ctx.dataset.yAxisID === 'severity') {
              return `${base}/5${ctx.raw.note ? ' — ' + ctx.raw.note : ''}`
            }
            if (ctx.dataset.yAxisID === 'kg') {
              return `${base} ${t('health.kg')}${ctx.raw.note ? ' — ' + ctx.raw.note : ''}`
            }
            if (ctx.dataset.yAxisID === 'bpm') return `${base} ${t('health.bpm')}`
            return base
          },
        },
      },
    },
    scales,
  }
}

/** Середнє значення поля за кожен день, точка — на полудень. */
function dailyAverage(events, field) {
  const byDate = new Map()
  for (const e of events) {
    const b = byDate.get(e.date) ?? { sum: 0, n: 0 }
    b.sum += e.payload[field]
    b.n += 1
    byDate.set(e.date, b)
  }
  return [...byDate.entries()].map(([date, { sum, n }]) => ({
    x: new Date(date + 'T12:00:00'),
    y: Math.round((sum / n) * 10) / 10,
  }))
}

/** Лінії САТ/ДАТ + пунктирний пульс на власній осі. */
function pressureDatasets(events, t, smoothPulse) {
  const points = (field) => events.map((e) => ({ x: eventMoment(e), y: e.payload[field] }))
  const line = (label, field, color, extra = {}) => ({
    label,
    data: points(field),
    borderColor: color,
    backgroundColor: color,
    tension: 0.25,
    ...extra,
  })
  // Для звіту пульс згладжується до середнього за день (сирі заміри надто «шумлять»)
  const pulseData = smoothPulse ? dailyAverage(events, 'pulse') : points('pulse')
  return [
    line(t('health.sbp'), 'systolic', PRESSURE_COLORS.sbp, { yAxisID: 'y' }),
    line(t('health.dbp'), 'diastolic', PRESSURE_COLORS.dbp, { yAxisID: 'y' }),
    {
      label: t('health.pulse'),
      data: pulseData,
      yAxisID: 'bpm',
      borderColor: PRESSURE_COLORS.pulse,
      backgroundColor: PRESSURE_COLORS.pulse,
      borderDash: [3, 3],
      borderWidth: smoothPulse ? 1.5 : undefined,
      pointRadius: smoothPulse ? 2 : undefined,
      tension: 0.25,
    },
  ]
}

/**
 * Датасети накладень (точка/стовпчик/лінія): тяжкість 1–5 — на осі severity,
 * вага — на осі кг. Події без числа стають маркерами (див. overlayAnnotations).
 */
function overlayDatasets(events, enabled, types, t) {
  const datasets = []
  for (const type of enabled) {
    if (type === 'pressure') continue
    const cfg = types[type]
    const ht = healthType(type)
    if (!cfg || !ht || cfg.style === 'marker') continue
    const points = events
      .filter((e) => e.type === type && (ht.kg ? e.payload.kg != null : e.payload.severity != null))
      .map((e) => ({
        x: eventMoment(e),
        y: ht.kg ? e.payload.kg : e.payload.severity,
        note: e.note,
      }))
    if (points.length === 0) continue
    datasets.push({
      type: cfg.style === 'bar' ? 'bar' : cfg.style === 'line' ? 'line' : 'scatter',
      label: `${ht.icon} ${t(`healthTypes.${type}`)}`,
      data: points,
      yAxisID: ht.kg ? 'kg' : 'severity',
      borderColor: cfg.color,
      backgroundColor: cfg.style === 'bar' ? cfg.color + '99' : cfg.color,
      pointStyle: ht.kg ? 'circle' : 'rectRot',
      pointRadius: ht.kg ? 3.5 : 6,
      barThickness: 10,
      tension: 0.25,
      spanGaps: true,
    })
  }
  return datasets
}

/** Маркери: вертикальна пунктирна лінія (є час) або смуга на весь день (часу немає). */
function overlayAnnotations(events, types) {
  const annotations = {}
  let i = 0
  for (const e of events) {
    if (e.type === 'pressure') continue
    const cfg = types[e.type]
    const ht = healthType(e.type)
    if (!ht) continue
    const hasValue = ht.kg ? e.payload.kg != null : ht.severity ? e.payload.severity != null : false
    if (cfg && cfg.style !== 'marker' && hasValue) continue
    const color = cfg?.color ?? ht.color
    const valueText = e.payload.severity
      ? ` ${e.payload.severity}/5`
      : e.payload.kg
        ? ` ${e.payload.kg}`
        : ''
    const label = {
      display: true,
      content: `${ht.icon}${valueText}`,
      position: 'end',
      backgroundColor: color,
      font: { size: 10 },
      padding: 3,
    }
    annotations['ev' + i++] = e.time
      ? {
          type: 'line',
          xMin: eventMoment(e),
          xMax: eventMoment(e),
          borderColor: color,
          borderWidth: 1.5,
          borderDash: [4, 4],
          label,
        }
      : {
          type: 'box',
          xMin: new Date(`${e.date}T00:00:00`),
          xMax: new Date(`${e.date}T23:59:59`),
          backgroundColor: color + '22',
          borderWidth: 0,
          label,
        }
  }
  return annotations
}

/**
 * Конфігурація Chart.js для подій `events` (уже відфільтрованих за періодом і людиною)
 * з увімкненими типами `enabled` та стилями `types` ({type: {color, style}}).
 * Повертає null, якщо малювати нічого.
 */
export function buildHealthChart({
  events,
  enabled,
  types,
  t,
  locale,
  animate = true,
  xRange = null,
  kgRange = null,
  smoothPulse = false,
}) {
  const visible = events.filter((e) => enabled.includes(e.type))
  if (visible.length === 0) return null
  const pressure = enabled.includes('pressure') ? visible.filter((e) => e.type === 'pressure') : []
  const overlays = overlayDatasets(visible, enabled, types, t)

  const annotations = {
    ...(pressure.length
      ? {
          sysNorm: normLine(HOME_NORM.sbp, t('health.sbpNorm'), PRESSURE_COLORS.sbp),
          diaNorm: normLine(HOME_NORM.dbp, t('health.dbpNorm'), PRESSURE_COLORS.dbp),
        }
      : {}),
    ...overlayAnnotations(visible, types),
  }

  return {
    type: 'line',
    data: {
      datasets: [
        ...(pressure.length ? pressureDatasets(pressure, t, smoothPulse) : []),
        ...overlays,
      ],
    },
    options: baseOptions({
      annotations,
      axes: {
        y: pressure.length > 0,
        bpm: pressure.length > 0,
        severity: overlays.some((d) => d.yAxisID === 'severity'),
        kg: overlays.some((d) => d.yAxisID === 'kg'),
      },
      t,
      locale,
      animate,
      xRange,
      kgRange,
    }),
  }
}
