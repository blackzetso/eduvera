/**
 * Swap Dova UI snapshots, build, and capture before/after screenshots.
 */
import { chromium } from 'playwright'
import { copyFile, mkdir, writeFile } from 'node:fs/promises'
import { spawn } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..')
const widgetPath = path.join(root, 'resources/js/Components/Dova/DovaWidget.vue')
const cssPath = path.join(root, 'resources/css/dova-widget.css')
const beforeDir = path.join(root, 'scripts/snapshots/dova-ui-before')
const afterDir = path.join(root, 'scripts/snapshots/dova-ui-after')
const outDir = path.join(root, 'docs/dova/screenshots')
const baseUrl = process.argv[2] ?? 'http://127.0.0.1:8000'

function run(command, args, cwd = root) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd, shell: true, stdio: 'inherit' })
    child.on('exit', (code) => (code === 0 ? resolve() : reject(new Error(`${command} exited ${code}`))))
  })
}

async function applySnapshot(dir) {
  await copyFile(path.join(dir, 'DovaWidget.vue'), widgetPath)
  await copyFile(path.join(dir, 'dova-widget.css'), cssPath)
}

async function capture(prefix) {
  const browser = await chromium.launch({ headless: true })
  const results = {}

  for (const [name, viewport] of [
    ['desktop', { width: 1440, height: 900 }],
    ['mobile', { width: 390, height: 844 }],
  ]) {
    const page = await browser.newPage({ viewport })
    await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 45000 })
    await page.locator('.dova-widget__launcher').click()
    await page.locator('.dova-widget__panel').waitFor({ state: 'visible', timeout: 15000 })
    await page.waitForTimeout(700)

    const panelPath = path.join(outDir, `${prefix}-panel-${name}.png`)
    const viewportPath = path.join(outDir, `${prefix}-viewport-${name}.png`)

    await page.locator('.dova-widget__panel').screenshot({ path: panelPath })
    await page.screenshot({ path: viewportPath })

    results[name] = await page.evaluate(() => {
      const panel = document.querySelector('.dova-widget__panel')
      const input = document.querySelector('.dova-widget__input')
      const hero = document.querySelector('.dova-widget__hero')
      const composer = document.querySelector('.dova-widget__composer')
      const inputRect = input?.getBoundingClientRect()
      const panelRect = panel?.getBoundingClientRect()
      const composerRect = composer?.getBoundingClientRect()

      return {
        heroHeight: Math.round(hero?.getBoundingClientRect().height ?? 0),
        panelHeight: Math.round(panelRect?.height ?? 0),
        inputVisible: Boolean(input && inputRect && inputRect.bottom <= window.innerHeight),
        composerVisible: Boolean(composer && composerRect && composerRect.bottom <= (panelRect?.bottom ?? 0) + 2),
        suggestionCount: document.querySelectorAll('.dova-widget__suggestion').length,
      }
    })

    await page.close()
  }

  await browser.close()
  return results
}

await mkdir(outDir, { recursive: true })

await applySnapshot(beforeDir)
await run('npm', ['run', 'build'])
const before = await capture('before')

await applySnapshot(afterDir)
await run('npm', ['run', 'build'])
const after = await capture('after')

const report = {
  capturedAt: new Date().toISOString(),
  baseUrl,
  before,
  after,
  files: [
    'docs/dova/screenshots/before-panel-desktop.png',
    'docs/dova/screenshots/before-panel-mobile.png',
    'docs/dova/screenshots/before-viewport-desktop.png',
    'docs/dova/screenshots/before-viewport-mobile.png',
    'docs/dova/screenshots/after-panel-desktop.png',
    'docs/dova/screenshots/after-panel-mobile.png',
    'docs/dova/screenshots/after-viewport-desktop.png',
    'docs/dova/screenshots/after-viewport-mobile.png',
  ],
}

await writeFile(path.join(outDir, 'before-after-report.json'), JSON.stringify(report, null, 2))
console.log(JSON.stringify(report, null, 2))
