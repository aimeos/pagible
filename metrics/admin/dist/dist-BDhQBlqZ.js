import { Chart as e, LineController as t } from "./chart-DmuErpZv.js";
import { defineComponent as n, h as r, isProxy as i, nextTick as a, onMounted as o, onUnmounted as s, ref as c, shallowRef as l, toRaw as u, version as d, watch as f } from "vue";
// ../../node_modules/vue-chartjs/dist/index.js
var p = {
	data: {
		type: Object,
		required: !0
	},
	options: {
		type: Object,
		default: () => ({})
	},
	plugins: {
		type: Array,
		default: () => []
	},
	datasetIdKey: {
		type: String,
		default: "label"
	},
	updateMode: {
		type: String,
		default: void 0
	}
}, m = {
	ariaLabel: { type: String },
	ariaDescribedby: { type: String }
}, h = {
	type: {
		type: String,
		required: !0
	},
	destroyDelay: {
		type: Number,
		default: 0
	},
	...p,
	...m
}, g = d[0] === "2" ? (e, t) => Object.assign(e, { attrs: t }) : (e, t) => Object.assign(e, t);
function _(e) {
	return i(e) ? u(e) : e;
}
function v(e) {
	let t = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : e;
	return i(t) ? new Proxy(e, {}) : e;
}
function y(e, t) {
	let n = e.options;
	n && t && Object.assign(n, t);
}
function b(e, t) {
	e.labels = t;
}
function x(e, t, n) {
	let r = [];
	e.datasets = t.map((t) => {
		let i = e.datasets.find((e) => e[n] === t[n]);
		return !i || !t.data || r.includes(i) ? { ...t } : (r.push(i), Object.assign(i, t), i);
	});
}
function S(e, t) {
	let n = {
		labels: [],
		datasets: []
	};
	return b(n, e.labels), x(n, e.datasets, t), n;
}
var C = n({
	props: h,
	setup(t, n) {
		let { expose: i, slots: d } = n, p = c(null), m = l(null);
		i({ chart: m });
		let h = () => {
			if (!p.value) return;
			let { type: n, data: r, options: i, plugins: a, datasetIdKey: o } = t, s = v(S(r, o), r);
			m.value = new e(p.value, {
				type: n,
				data: s,
				options: { ...i },
				plugins: a
			});
		}, g = () => {
			let e = u(m.value);
			e && (t.destroyDelay > 0 ? setTimeout(() => {
				e.destroy(), m.value = null;
			}, t.destroyDelay) : (e.destroy(), m.value = null));
		}, C = (e) => {
			e.update(t.updateMode);
		};
		return o(h), s(g), f([() => t.options, () => t.data], (e, n) => {
			let [r, i] = e, [o, s] = n, c = u(m.value);
			if (!c) return;
			let l = !1;
			if (r) {
				let e = _(r), t = _(o);
				e && e !== t && (y(c, e), l = !0);
			}
			if (i) {
				let e = _(i.labels), n = _(s.labels), r = _(i.datasets), a = _(s.datasets);
				e !== n && (b(c.config.data, e), l = !0), r && r !== a && (x(c.config.data, r, t.datasetIdKey), l = !0);
			}
			l && a(() => {
				C(c);
			});
		}, { deep: !0 }), () => r("canvas", {
			role: "img",
			"aria-label": t.ariaLabel,
			"aria-describedby": t.ariaDescribedby,
			ref: p
		}, [r("p", {}, [d.default ? d.default() : ""])]);
	}
});
function w(t, i) {
	return e.register(i), n({
		props: p,
		setup(e, n) {
			let { expose: i } = n, a = l(null), o = l(null);
			f(() => o.value?.chart ?? null, (e) => {
				a.value = e;
			}, { flush: "sync" }), i({ chart: a });
			let s = (e) => {
				o.value = e;
			};
			return () => r(C, g({ ref: s }, {
				type: t,
				...e
			}));
		}
	});
}
var T = /* #__PURE__ */ w("line", t);
export { T as Line };
