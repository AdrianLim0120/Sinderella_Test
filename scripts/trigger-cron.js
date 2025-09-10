// scripts/trigger-cron.js
const puppeteer = require("puppeteer");

(async () => {
  const url = process.env.CRON_URL;        // e.g. https://sindtest.free.nf/cron/remind_2days.php?internal_key=...
  const dryrun = process.env.DRYRUN === "1"; // set to 1 for safe testing
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

  // 1) Visit once to pass the __test cookie wall (JS runs in headless Chrome)
  await page.goto(triggerUrl, { waitUntil: "networkidle2", timeout: 120000 });

  // 2) Wait a moment and grab the resulting body text (should be JSON from your PHP)
  await page.waitForTimeout(1500);
  const body = await page.evaluate(() => document.body.innerText || document.documentElement.innerText);

  console.log("=== CRON OUTPUT START ===");
  console.log(body);
  console.log("=== CRON OUTPUT END ===");

  // Optional basic success check
  if (!/^\s*\{/.test(body)) {
    console.warn("Output is not JSON (maybe still behind a wall).");
  }

  await browser.close();
})();
