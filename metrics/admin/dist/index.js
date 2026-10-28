if (typeof document !== "undefined") {const s = document.createElement("style");s.dataset.cms = "metrics";s.textContent = ".loading-overlay[data-v-47d10b10]{background:rgba(var(--v-theme-surface), .4);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);z-index:10;inset:0}.emphasis-bg[data-v-47d10b10]{background-color:rgb(var(--v-theme-emphasis,var(--v-theme-surface-light)));color:rgb(var(--v-theme-on-emphasis,var(--v-theme-on-surface-light)))}.good[data-v-47d10b10]{color:color-mix(in srgb, rgb(var(--v-theme-success)) 80%, rgb(var(--v-theme-on-emphasis,var(--v-theme-on-surface))))}.bad[data-v-47d10b10]{color:color-mix(in srgb, rgb(var(--v-theme-error)) 80%, rgb(var(--v-theme-on-emphasis,var(--v-theme-on-surface))))}.warn[data-v-47d10b10]{color:color-mix(in srgb, rgb(var(--v-theme-warning)) 80%, rgb(var(--v-theme-on-emphasis,var(--v-theme-on-surface))))}\n/*$vite$:1*/";document.head.appendChild(s);}
import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, defineAsyncComponent as c, markRaw as l, normalizeClass as u, openBlock as d, renderList as f, resolveComponent as p, toDisplayString as m, withCtx as h } from "vue";
// ../../node_modules/@mdi/js/mdi.js
var g = "M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z", _ = "M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z", v = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, y = c(() => Promise.all([import("./dist-BDhQBlqZ.js").then((e) => e.Line), import("./chart-DmuErpZv.js").then(({ Chart: e, Title: t, Tooltip: n, Legend: r, LineElement: i, PointElement: a, CategoryScale: o, LinearScale: s }) => {
	e.register(t, n, r, i, a, o, s);
})]).then(([e]) => e)), b = {
	maintainAspectRatio: !1,
	responsive: !0,
	interaction: {
		mode: "index",
		intersect: !1
	}
}, x = {
	components: { LineChart: y },
	props: { charts: {
		type: Array,
		required: !0
	} },
	computed: { options() {
		let e = this.$vuetify.theme.current.colors, t = this.$vuetify.locale.isRtl, n = e?.["text-secondary"] || e?.["on-surface"], r = e?.["border-light"];
		return l({
			...b,
			locale: this.$vuetify.locale.current,
			plugins: {
				legend: {
					labels: { color: n },
					rtl: t
				},
				tooltip: {
					intersect: !1,
					rtl: t
				}
			},
			scales: {
				x: {
					reverse: t,
					ticks: { color: n },
					grid: { color: r }
				},
				y: {
					beginAtZero: !0,
					position: t ? "right" : "left",
					ticks: { color: n },
					grid: { color: r }
				}
			}
		});
	} }
};
function S(e, a, c, l, u, g) {
	let _ = p("v-card-title"), v = p("LineChart"), y = p("v-card-text"), b = p("v-card"), x = p("v-col"), S = p("v-row");
	return d(), n(S, null, {
		default: h(() => [(d(!0), i(t, null, f(c.charts, (e) => (d(), i(t, { key: e.title }, [e.show ? (d(), n(x, {
			key: 0,
			cols: "12",
			md: "6"
		}, {
			default: h(() => [s(b, { class: "mt-4" }, {
				default: h(() => [s(_, { class: "text-title-medium" }, {
					default: h(() => [o(m(e.title), 1)]),
					_: 2
				}, 1024), s(y, { style: { "aspect-ratio": "3 / 2" } }, {
					default: h(() => [s(v, {
						options: g.options,
						data: e.data
					}, null, 8, ["options", "data"])]),
					_: 2
				}, 1024)]),
				_: 2
			}, 1024)]),
			_: 2
		}, 1024)) : r("", !0)], 64))), 128))]),
		_: 1
	});
}
var C = /*#__PURE__*/ v(x, [["render", S]]);
// src/metrics.js
function w(e) {
	try {
		return JSON.parse(e)?.error?.message || e;
	} catch {
		return e;
	}
}
function T(e, t = void 0) {
	let n = (e, n) => new Intl.NumberFormat(t, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2
	}).format(e) + n;
	switch (!0) {
		case e >= 1e9: return n(e / 1e9, "B");
		case e >= 1e6: return n(e / 1e6, "M");
		case e >= 1e3: return n(e / 1e3, "K");
	}
	return new Intl.NumberFormat(t, { maximumFractionDigits: 2 }).format(e);
}
function E(e, t = void 0) {
	return e.average ? new Intl.NumberFormat(t, {
		minimumFractionDigits: 1,
		maximumFractionDigits: 1
	}).format(F(e.data) / 7) + (e.suffix || "") : T(F(e.data), t);
}
function D(e, t) {
	let n = (t - 1) * 10;
	return e.slice(n, n + 10);
}
function O(e, t = void 0) {
	return [...e || []].sort(L).map((e) => ({
		...e,
		value: T(e.value, t)
	}));
}
function k(e, t, n) {
	return e == null ? "" : e < t ? "good" : e >= n ? "bad" : "warn";
}
function A(e) {
	return (e > 0 ? "+" : "") + Number(e).toFixed(0);
}
function j(e) {
	return [...e].sort(L);
}
function M(e, t, n = null) {
	return [...e || []].sort(I).map((e) => {
		let r = {
			...e,
			key: t.format(new Date(e.key))
		};
		return n?.(r), r;
	});
}
function N(e) {
	return e.value = Math.round(e.value / 6) / 10, e;
}
function P(e) {
	return e.value = Math.round(e.value * 1e3) / 10, e;
}
function F(e, t = 1) {
	return e.slice(-7 * t, e.length - 7 * (t - 1)).reduce((e, t) => e + Number(t.value), 0);
}
function I(e, t) {
	return e.key > t.key ? 1 : e.key < t.key ? -1 : 0;
}
function L(e, t) {
	return t.value - e.value;
}
// src/components/PageDetailMetrics.vue
var R = [30, 60], z = [
	{
		key: "data-table-expand",
		width: 1
	},
	{ key: "key" },
	{
		key: "value",
		align: "end"
	}
], B = [{ key: "key" }, {
	key: "value",
	align: "end"
}], V = e`
  mutation ($url: String!, $days: Int) {
    metrics(url: $url, days: $days) {
      views { key value }
      visits { key value }
      conversions { key value }
      durations { key value }
      countries { key value }
      referrers { key value rows { key value } }
      pagespeed { key value }
      impressions { key value }
      clicks { key value }
      ctrs { key value }
      queries { key impressions clicks ctr position }
      errors
    }
  }
`, H = {
	components: { MetricsCharts: C },
	inject: ["apollo", "pageUrl"],
	props: { item: {
		type: Object,
		required: !0
	} },
	data: () => ({
		request: 0,
		days: 30,
		errors: [],
		loading: !1,
		pagespeed: null,
		conversions: [],
		countries: [],
		durations: [],
		referrers: [],
		impressions: [],
		queries: [],
		ctrs: [],
		clicks: [],
		visits: [],
		views: [],
		querypage: 1
	}),
	setup() {
		return {
			mdiChevronUp: _,
			mdiChevronDown: g,
			DAYS_OPTIONS: R,
			REFERRER_HEADERS: z,
			COUNTRY_HEADERS: B,
			QUERY_PAGE_SIZE: 10,
			apiError: w,
			compact: T,
			insightValue: E,
			paginate: D,
			rating: k,
			signed: A,
			sortedByValue: j
		};
	},
	created() {
		this.dateFormatter = new Intl.DateTimeFormat(this.$vuetify.locale.current, {
			day: "numeric",
			month: "numeric"
		});
	},
	mounted() {
		this.metrics();
	},
	beforeUnmount() {
		this.request++;
	},
	computed: {
		charts() {
			let e = this.palette;
			return [
				{
					show: this.views.length || this.visits.length || this.conversions.length,
					title: this.$pgettext("metrics", "Views & Visits"),
					data: this.chart(this.views, [
						e.primary,
						this.$pgettext("metrics", "Views"),
						this.views
					], [
						e.warning,
						this.$pgettext("metrics", "Visits"),
						this.visits
					], [
						e.success,
						this.$pgettext("metrics", "Conversions"),
						this.conversions
					])
				},
				{
					show: this.durations.length,
					title: this.$pgettext("metrics", "Visit Durations (minutes)"),
					data: this.chart(this.durations, [
						e.primary,
						this.$pgettext("metrics", "Duration"),
						this.durations
					])
				},
				{
					show: this.impressions.length || this.clicks.length,
					title: this.$pgettext("metrics", "Google Search: Impressions & Clicks"),
					data: this.chart(this.impressions, [
						e.primary,
						this.$pgettext("metrics", "Impressions"),
						this.impressions
					], [
						e.warning,
						this.$pgettext("metrics", "Clicks"),
						this.clicks
					])
				},
				{
					show: this.ctrs.length,
					title: this.$pgettext("metrics", "Google Search: Click-through rate"),
					data: this.chart(this.ctrs, [
						e.success,
						this.$pgettext("metrics", "Percentage"),
						this.ctrs
					])
				}
			];
		},
		colors() {
			return this.$vuetify.theme.current.colors;
		},
		palette() {
			return {
				primary: this.colors?.primary,
				warning: this.colors?.warning,
				success: this.colors?.success
			};
		},
		insightColumns() {
			let e = this.conversions.reduce((e, t) => e + Number(t.value), 0);
			return l([
				{
					label: this.$pgettext("metrics", "Page Views"),
					data: this.views
				},
				{
					label: this.$pgettext("metrics", "Page Visits"),
					data: this.visits
				},
				e ? {
					label: this.$pgettext("metrics", "Conversions"),
					data: this.conversions,
					average: !0
				} : {
					label: this.$pgettext("metrics", "Visit Duration (minutes)"),
					data: this.durations,
					average: !0
				},
				{
					label: this.$pgettext("metrics", "Google Impressions"),
					data: this.impressions
				},
				{
					label: this.$pgettext("metrics", "Google Clicks"),
					data: this.clicks
				},
				{
					label: this.$pgettext("metrics", "Google click-through rate"),
					data: this.ctrs,
					average: !0,
					suffix: "%"
				}
			].map((e) => ({
				...e,
				current: F(e.data),
				previous: F(e.data, 2)
			})));
		},
		speedMetrics() {
			return l([
				{
					key: "round_trip_time",
					label: this.$pgettext("metrics", "Round trip time"),
					good: 200,
					bad: 500,
					unit: "ms"
				},
				{
					key: "time_to_first_byte",
					label: this.$pgettext("metrics", "Time to first byte"),
					good: 800,
					bad: 1800,
					unit: "ms"
				},
				{
					key: "first_contentful_paint",
					label: this.$pgettext("metrics", "First contentful paint"),
					good: 1800,
					bad: 3e3,
					unit: "ms"
				},
				{
					key: "largest_contentful_paint",
					label: this.$pgettext("metrics", "Largest contentful paint"),
					good: 2500,
					bad: 4e3,
					unit: "ms"
				},
				{
					key: "interaction_to_next_paint",
					label: this.$pgettext("metrics", "Interaction to next paint"),
					good: 200,
					bad: 500,
					unit: "ms"
				},
				{
					key: "cumulative_layout_shift",
					label: this.$pgettext("metrics", "Cumulative layout shift"),
					good: .1,
					bad: .25,
					unit: ""
				}
			]);
		}
	},
	methods: {
		chart(e, ...t) {
			return l({
				labels: e.map((e) => e.key),
				...t.length > 1 ? { grouped: !0 } : {},
				datasets: t.map(([e, t, n]) => ({
					borderWidth: 2,
					borderColor: e,
					backgroundColor: e,
					label: t,
					data: n.map((e) => e.value),
					pointRadius: 0,
					tension: .2
				}))
			});
		},
		clear() {
			for (let e of [
				"conversions",
				"countries",
				"durations",
				"referrers",
				"impressions",
				"queries",
				"ctrs",
				"clicks",
				"visits",
				"views"
			]) this[e] = [];
			this.pagespeed = null, this.querypage = 1;
		},
		async metrics() {
			let e = ++this.request;
			this.errors = [], this.loading = !0;
			try {
				let { data: t } = await this.apollo.mutate({
					mutation: V,
					variables: {
						url: this.url(this.item),
						days: this.days
					}
				});
				if (e !== this.request) return;
				let n = t?.metrics || {};
				this.views = Object.freeze(M(n.views, this.dateFormatter)), this.visits = Object.freeze(M(n.visits, this.dateFormatter)), this.conversions = Object.freeze(M(n.conversions, this.dateFormatter)), this.durations = Object.freeze(M(n.durations, this.dateFormatter, N)), this.impressions = Object.freeze(M(n.impressions, this.dateFormatter)), this.clicks = Object.freeze(M(n.clicks, this.dateFormatter)), this.ctrs = Object.freeze(M(n.ctrs, this.dateFormatter, P)), this.countries = Object.freeze(O(n.countries, this.$vuetify.locale.current).map((e) => Object.freeze(e))), this.referrers = Object.freeze(O(n.referrers, this.$vuetify.locale.current).map((e) => Object.freeze({
					...e,
					rows: Object.freeze((e.rows || []).map((e) => Object.freeze(e)))
				}))), this.querypage = 1, this.queries = Object.freeze([...n.queries || []].sort((e, t) => t.impressions - e.impressions).map((e) => Object.freeze(e))), this.pagespeed = Object.freeze(n?.pagespeed?.reduce((e, { key: t, value: n }) => (e[t] = n, e), {})), this.errors = n.errors || [];
			} catch (t) {
				e === this.request && (this.clear(), this.errors.push(this.$pgettext("metrics", "Error fetching metrics")), this.$log("PageDetailMetrics::metrics(): Error fetching metrics", t));
			} finally {
				e === this.request && (this.loading = !1);
			}
		},
		url(e) {
			return this.pageUrl(e, !0);
		}
	}
}, U = {
	key: 0,
	class: "loading-overlay position-absolute d-flex align-center justify-center"
}, W = { class: "text-caption opacity-70" }, G = { class: "d-flex align-center justify-space-between text-h6" }, K = { key: 0 }, q = { key: 1 }, J = { key: 0 }, Y = ["colspan"], X = { class: "v-data-table-column--align-start" }, Z = { class: "v-data-table-column--align-end" }, Q = { class: "text-caption opacity-70" }, $ = { key: 0 }, ee = { key: 1 };
function te(e, c, l, g, _, v) {
	let y = p("v-col"), b = p("v-select"), x = p("v-row"), S = p("v-alert"), C = p("CmsLoadingSpinner"), w = p("v-card-title"), T = p("v-card-text"), E = p("v-card"), D = p("MetricsCharts"), O = p("v-icon"), k = p("v-data-table"), A = p("v-pagination"), j = p("v-card-actions"), M = p("v-sheet"), N = p("v-container");
	return d(), n(N, null, {
		default: h(() => [s(M, {
			class: "box scroll",
			style: { height: "var(--cms-detail-height)" }
		}, {
			default: h(() => [
				s(x, null, {
					default: h(() => [s(y, {
						cols: "6",
						class: "d-flex align-self-center text-title-large font-weight-medium"
					}, {
						default: h(() => [o(m(e.$pgettext("metrics", "Page metrics")), 1)]),
						_: 1
					}), s(y, {
						cols: "6",
						class: "d-flex justify-end align-self-center"
					}, {
						default: h(() => [s(b, {
							modelValue: e.days,
							"onUpdate:modelValue": [c[0] ||= (t) => e.days = t, v.metrics],
							items: g.DAYS_OPTIONS,
							label: e.$pgettext("metrics", "Days"),
							variant: "underlined",
							"max-width": "120",
							"hide-details": ""
						}, null, 8, [
							"modelValue",
							"items",
							"label",
							"onUpdate:modelValue"
						])]),
						_: 1
					})]),
					_: 1
				}),
				(d(!0), i(t, null, f(e.errors, (e, t) => (d(), n(S, {
					key: t,
					variant: "tonal",
					border: "start",
					class: "mt-4",
					type: "error"
				}, {
					default: h(() => [o(m(g.apiError(e)), 1)]),
					_: 2
				}, 1024))), 128)),
				e.loading ? (d(), i("div", U, [s(C, {
					width: "32",
					height: "32"
				})])) : r("", !0),
				s(x, null, {
					default: h(() => [s(y, { cols: "12" }, {
						default: h(() => [s(E, { class: "mt-4 emphasis-bg" }, {
							default: h(() => [s(w, { class: "text-title-medium" }, {
								default: h(() => [o(m(e.$pgettext("metrics", "Weekly Insights")), 1)]),
								_: 1
							}), s(T, null, {
								default: h(() => [s(x, null, {
									default: h(() => [(d(!0), i(t, null, f(v.insightColumns, (t, o) => (d(), n(y, {
										key: o,
										cols: "6",
										md: "4",
										lg: "2"
									}, {
										default: h(() => [a("div", W, m(t.label), 1), a("div", G, [t.data.length ? (d(), i("div", K, [a("span", null, m(g.insightValue(t, e.$vuetify.locale.current)), 1), t.previous ? (d(), i("span", {
											key: 0,
											class: u(["text-body-large ms-3", t.current >= t.previous ? "good" : "bad"])
										}, m(g.signed(t.current * 100 / t.previous - 100)) + "% ", 3)) : r("", !0)])) : (d(), i("div", q, "—"))])]),
										_: 2
									}, 1024))), 128))]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})]),
					_: 1
				}),
				s(D, { charts: v.charts.slice(0, 2) }, null, 8, ["charts"]),
				s(x, null, {
					default: h(() => [e.referrers.length ? (d(), n(y, {
						key: 0,
						cols: "12",
						md: "6"
					}, {
						default: h(() => [s(E, { class: "mt-4" }, {
							default: h(() => [s(w, { class: "text-title-medium" }, {
								default: h(() => [o(m(e.$pgettext("metrics", "Referrers")), 1)]),
								_: 1
							}), s(T, null, {
								default: h(() => [s(k, {
									items: e.referrers,
									headers: g.REFERRER_HEADERS,
									"item-value": "key",
									"items-per-page": "25",
									"hide-default-header": "",
									"hide-default-footer": "",
									"expand-on-click": "",
									"show-expand": ""
								}, {
									"item.data-table-expand": h(({ internalItem: e, isExpanded: t }) => [e.raw?.rows?.length ? (d(), n(O, { key: 0 }, {
										default: h(() => [o(m(t(e) ? g.mdiChevronUp : g.mdiChevronDown), 1)]),
										_: 2
									}, 1024)) : r("", !0)]),
									"expanded-row": h(({ columns: t, item: n }) => [n.rows.length ? (d(), i("tr", J, [a("td", {
										colspan: t.length,
										class: "py-2"
									}, [s(k, {
										items: g.sortedByValue(n.rows),
										density: "compact",
										"hide-default-header": "",
										hover: ""
									}, {
										item: h(({ item: t }) => [a("tr", null, [a("td", X, m(t.key), 1), a("td", Z, m(g.compact(t.value, e.$vuetify.locale.current)), 1)])]),
										_: 2
									}, 1032, ["items"])], 8, Y)])) : r("", !0)]),
									_: 2
								}, 1032, ["items", "headers"])]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})) : r("", !0), e.countries.length ? (d(), n(y, {
						key: 1,
						cols: "12",
						md: "6"
					}, {
						default: h(() => [s(E, { class: "mt-4" }, {
							default: h(() => [s(w, { class: "text-title-medium" }, {
								default: h(() => [o(m(e.$pgettext("metrics", "Countries")), 1)]),
								_: 1
							}), s(T, null, {
								default: h(() => [s(k, {
									items: e.countries,
									headers: g.COUNTRY_HEADERS,
									"hide-default-header": ""
								}, null, 8, ["items", "headers"])]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})) : r("", !0)]),
					_: 1
				}),
				s(x, null, {
					default: h(() => [e.pagespeed ? (d(), n(y, {
						key: 0,
						cols: "12"
					}, {
						default: h(() => [s(E, { class: "mt-4 emphasis-bg" }, {
							default: h(() => [s(w, { class: "text-title-medium" }, {
								default: h(() => [o(m(e.$pgettext("metrics", "Page Speed")), 1)]),
								_: 1
							}), s(T, null, {
								default: h(() => [s(x, null, {
									default: h(() => [(d(!0), i(t, null, f(v.speedMetrics, (t) => (d(), n(y, {
										key: t.key,
										cols: "6",
										md: "4",
										lg: "2"
									}, {
										default: h(() => [a("div", Q, m(t.label), 1), a("div", { class: u(["d-flex align-center justify-space-between text-h6", g.rating(e.pagespeed?.[t.key], t.good, t.bad)]) }, [typeof e.pagespeed?.[t.key] == "number" ? (d(), i("span", $, m(e.pagespeed?.[t.key]) + m(t.unit), 1)) : (d(), i("span", ee, "—"))], 2)]),
										_: 2
									}, 1024))), 128))]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})) : r("", !0)]),
					_: 1
				}),
				s(x, null, {
					default: h(() => [e.queries.length ? (d(), n(y, {
						key: 0,
						cols: "12"
					}, {
						default: h(() => [s(E, { class: "mt-4" }, {
							default: h(() => [
								s(w, { class: "text-title-medium" }, {
									default: h(() => [o(m(e.$pgettext("metrics", "Google Search: Queries")), 1)]),
									_: 1
								}),
								s(T, null, {
									default: h(() => [s(x, { class: "font-weight-bold" }, {
										default: h(() => [s(y, {
											cols: "12",
											sm: "6"
										}), s(y, {
											cols: "12",
											sm: "6"
										}, {
											default: h(() => [s(x, null, {
												default: h(() => [
													s(y, { cols: "3" }, {
														default: h(() => [o(m(e.$pgettext("metrics", "Impressions")), 1)]),
														_: 1
													}),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(e.$pgettext("metrics", "Clicks")), 1)]),
														_: 1
													}),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(e.$pgettext("metrics", "Percent")), 1)]),
														_: 1
													}),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(e.$pgettext("search ranking", "Position")), 1)]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}), (d(!0), i(t, null, f(g.paginate(e.queries, e.querypage), (t, r) => (d(), n(x, {
										key: r,
										class: "border-t py-2"
									}, {
										default: h(() => [s(y, {
											cols: "12",
											sm: "6",
											class: "py-1 font-weight-bold"
										}, {
											default: h(() => [o(m(t.key), 1)]),
											_: 2
										}, 1024), s(y, {
											cols: "12",
											sm: "6",
											class: "py-1"
										}, {
											default: h(() => [s(x, null, {
												default: h(() => [
													s(y, { cols: "3" }, {
														default: h(() => [o(m(g.compact(t.impressions, e.$vuetify.locale.current)), 1)]),
														_: 2
													}, 1024),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(g.compact(t.clicks, e.$vuetify.locale.current)), 1)]),
														_: 2
													}, 1024),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(Number(t.ctr * 100).toFixed(1)), 1)]),
														_: 2
													}, 1024),
													s(y, { cols: "3" }, {
														default: h(() => [o(m(Number(t.position).toFixed(1)), 1)]),
														_: 2
													}, 1024)
												]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024))), 128))]),
									_: 1
								}),
								e.queries.length > g.QUERY_PAGE_SIZE ? (d(), n(j, {
									key: 0,
									class: "justify-center"
								}, {
									default: h(() => [s(A, {
										modelValue: e.querypage,
										"onUpdate:modelValue": c[1] ||= (t) => e.querypage = t,
										length: Math.ceil(e.queries.length / g.QUERY_PAGE_SIZE)
									}, null, 8, ["modelValue", "length"])]),
									_: 1
								})) : r("", !0)
							]),
							_: 1
						})]),
						_: 1
					})) : r("", !0)]),
					_: 1
				}),
				s(D, { charts: v.charts.slice(2) }, null, 8, ["charts"])
			]),
			_: 1
		})]),
		_: 1
	});
}
var ne = /*#__PURE__*/ v(H, [["render", te], ["__scopeId", "data-v-47d10b10"]]);
export { ne as default };
