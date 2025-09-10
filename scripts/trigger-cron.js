// scripts/trigger-cron.js
const puppeteer = require("puppeteer");

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const url = process.env.CRON_URL;
  const dryrun = process.env.DRYRUN === "1";
  if (!url) {
    console.error("CRON_URL env is missing");
    process.exit(1);
  }
  const triggerUrl = url + (url.includes("?") ? "&" : "?") + (dryrun ? "dryrun=1" : "");

  const browser = await puppeteer.launch({
    headless: "new",
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(120000);

  // Hit the URL; InfinityFree will run JS to set __test cookie then redirect
  await page.goto(triggerUrl, { waitUntil: "domcontentloaded" });

  // Let the redirect + PHP finish; poll the page body for JSON
  let body = "";
  for (let i = 0; i < 20; i++) {          // ~20s max
    // try to complete any pending navigation quietly
    try { await page.waitForNavigation({ waitUntil: "networkidle2", timeout: 1000 }); } catch (_) {}
    body = await page.evaluate(() => document.body?.innerText || "");
    if (body.trim().startsWith("{") || body.includes('"ok":')) break; // likely your JSON
    await sleep(1000);
  }

  console.log("=== CRON OUTPUT START ===");
  console.log(body);
  console.log("=== CRON OUTPUT END ===");

  await browser.close();

  // Non-JSON usually means the JS wall wasn’t passed yet
  if (!body.trim().startsWith("{")) process.exit(1);
})();
