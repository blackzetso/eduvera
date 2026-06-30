/**
 * Capture Dova widget UI screenshots (welcome state).
 * Usage: node scripts/capture-dova-ui-screenshots.mjs [prefix] [baseUrl]
 * Example: node scripts/capture-dova-ui-screenshots.mjs after
 */
import { chromium } from 'playwright'
import { mkdir } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const prefix = process.argv[2] ?? 'after'
const baseUrl = process.argv[3] ?? 'http://127.0.0.1:8000'
const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..')
const outDir = path.join(root, 'docs', 'dova', 'screenshots')

async function capture(viewport, suffix) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage({ viewport })

  await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 45000 })

  const launcher = page.locator('.dova-widget__launcher')
  await launcher.waitFor({ state: 'visible', timeout: 20000 })
  await launcher.click()

  const panel = page.locator('.dova-widget__panel')
  await panel.waitFor({ state: 'visible', timeout: 15000 })
  await page.waitForTimeout(600)

  const panelShot = path.join(outDir, `${prefix}-panel-${suffix}.png`)
  const viewportShot = path.join(outDir, `${prefix}-viewport-${suffix}.png`)

  await panel.screenshot({ path: panelShot })
  await page.screenshot({ path: viewportShot, fullPage: false })

  const checks = await page.evaluate(() => {
    const panel = document.querySelector('.dova-widget__panel')
    const input = document.querySelector('.dova-widget__input')
    const suggestions = document.querySelectorAll('.dova-widget__suggestion').length
    const welcome = document.querySelector('.dova-widget__welcome-card')
    const composer = document.querySelector('.dova-widget__composer')
    const hero = document.querySelector('.dova-widget__hero')

    if (!panel || !input || !composer || !hero) {
      return { ok: false }
    }

    const panelRect = panel.getBoundingClientRect()
    const inputRect = input.getBoundingClientRect()
    const composerRect = composer.getBoundingClientRect()

    return {
      ok: true,
      suggestions,
      welcomeVisible: Boolean(welcome),
      inputVisible: inputRect.bottom <= window.innerHeight && inputRect.top >= 0,
      composerInPanel: composerRect.bottom <= panelRect.bottom + 1,
      heroHeight: Math.round(hero.getBoundingClientRect().height),
      panelHeight: Math.round(panelRect.height),
    }
  })

  await browser.close()

  return { panelShot, viewportShot, checks }
}

await mkdir(outDir, { recursive: true })

const desktop = await capture({ width: 1440, height: 900 }, 'desktop')
const mobile = await capture({ width: 390, height: 844 }, 'mobile')

console.log(JSON.stringify({ prefix, desktop, mobile }, null, 2))

if (!desktop.checks.ok || !mobile.checks.ok) {
  process.exit(1)
}
