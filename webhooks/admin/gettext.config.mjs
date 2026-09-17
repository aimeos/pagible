import { readFileSync } from "node:fs";

const locales = readFileSync(new URL("./i18n/LINGUAS", import.meta.url), "utf8")
  .trim()
  .split(/\s+/);

export default {
  input: {
    path: "./src",
    include: ["**/*.js", "**/*.vue"],
  },
  output: {
    locales,
    path: "./i18n",
    jsonPath: "../public/i18n",
    splitJson: true,
    fuzzyMatching: false,
  },
};
