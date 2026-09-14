import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, openBlock as c, renderList as l, resolveComponent as u, toDisplayString as d, withCtx as f } from "vue";
//#region \0plugin-vue:export-helper
var p = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, m = e`
  fragment CmsWebhookFields on CmsWebhook {
    id
    status
    failures
    endpoint
    events
    last_error
  }
`, h = e`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
  }
  ${m}
`, g = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${m}
`, _ = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${m}
`, v = e`
  mutation ReplaceWebhook($id: ID!, $url: String!) {
    replaceWebhook(id: $id, url: $url) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${m}
`, y = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${m}
`, b = e`
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`, x = {
	name: "WebhookList",
	inject: ["apollo"],
	props: { panel: {
		type: Object,
		required: !0
	} },
	data: () => ({
		dialog: !1,
		replaceDialog: !1,
		secretDialog: !1,
		loading: !0,
		saving: !1,
		items: [],
		names: [],
		selected: null,
		url: "",
		events: [],
		status: !1,
		secret: "",
		message: "",
		messageColor: "info",
		messageOpen: !1
	}),
	mounted() {
		this.load();
	},
	methods: {
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: h,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.names = e.cmsWebhookEvents;
			} catch (e) {
				this.notify(this.$gettext("Error fetching webhooks") + ":\n" + e, "error");
			} finally {
				this.loading = !1;
			}
		},
		openAdd() {
			this.selected = null, this.url = "", this.events = [], this.status = !1, this.dialog = !0;
		},
		openEdit(e) {
			this.selected = e, this.events = [...e.events], this.status = e.status, this.dialog = !0;
		},
		openReplace(e) {
			this.selected = e, this.url = "", this.replaceDialog = !0;
		},
		async save() {
			if (!this.saving && this.events.length && (this.selected || this.url.trim())) {
				this.saving = !0;
				try {
					if (this.selected) {
						let { data: e } = await this.apollo.mutate({
							mutation: _,
							variables: {
								id: this.selected.id,
								input: {
									events: this.events,
									status: this.status
								}
							}
						});
						this.replaceItem(e.saveWebhook);
					} else {
						let { data: e } = await this.apollo.mutate({
							mutation: g,
							variables: { input: {
								url: this.url.trim(),
								events: this.events
							} }
						});
						this.items.unshift(e.addWebhook.webhook), this.showSecret(e.addWebhook.secret);
					}
					this.dialog = !1;
				} catch (e) {
					this.notify(this.$gettext("Error saving webhook") + ":\n" + e, "error");
				} finally {
					this.saving = !1;
				}
			}
		},
		async replace() {
			if (!this.saving && this.selected && this.url.trim()) {
				this.saving = !0;
				try {
					let { data: e } = await this.apollo.mutate({
						mutation: v,
						variables: {
							id: this.selected.id,
							url: this.url.trim()
						}
					});
					this.replaceItem(e.replaceWebhook.webhook), this.replaceDialog = !1, this.showSecret(e.replaceWebhook.secret);
				} catch (e) {
					this.notify(this.$gettext("Error replacing webhook destination") + ":\n" + e, "error");
				} finally {
					this.saving = !1;
				}
			}
		},
		async rotate(e) {
			if (!this.saving) {
				this.saving = !0;
				try {
					let { data: t } = await this.apollo.mutate({
						mutation: y,
						variables: { id: e.id }
					});
					this.replaceItem(t.rotateWebhook.webhook), this.showSecret(t.rotateWebhook.secret);
				} catch (e) {
					this.notify(this.$gettext("Error rotating webhook secret") + ":\n" + e, "error");
				} finally {
					this.saving = !1;
				}
			}
		},
		async remove(e) {
			if (!this.saving && window.confirm(this.$gettext("Delete this webhook?"))) {
				this.saving = !0;
				try {
					await this.apollo.mutate({
						mutation: b,
						variables: { id: [e.id] }
					}), this.items = this.items.filter((t) => t.id !== e.id);
				} catch (e) {
					this.notify(this.$gettext("Error deleting webhook") + ":\n" + e, "error");
				} finally {
					this.saving = !1;
				}
			}
		},
		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret), this.notify(this.$gettext("Secret copied"), "success");
			} catch {
				this.notify(this.$gettext("Unable to copy secret"), "error");
			}
		},
		errorText(e) {
			if (!e.last_error) return this.$gettext("None");
			let t = e.last_error.status ? ` (${e.last_error.status})` : "";
			return `${{
				delivery_failed: this.$gettext("Delivery failed"),
				destination_not_allowed: this.$gettext("Access denied"),
				http_error: this.$gettext("Delivery failed"),
				invalid_header: this.$gettext("Value has invalid format"),
				invalid_url: this.$gettext("Not a valid URL"),
				resolution_failed: this.$gettext("Delivery failed"),
				response_body_too_large: this.$gettext("Delivery failed"),
				response_headers_too_large: this.$gettext("Delivery failed"),
				transport_error: this.$gettext("Delivery failed"),
				transport_unavailable: this.$gettext("Delivery failed")
			}[e.last_error.reason] || this.$gettext("Delivery failed")}${t}`;
		},
		replaceItem(e) {
			let t = this.items.findIndex((t) => t.id === e.id);
			t >= 0 && this.items.splice(t, 1, e);
		},
		showSecret(e) {
			this.secret = e, this.secretDialog = !0;
		},
		notify(e, t) {
			this.message = e, this.messageColor = t, this.messageOpen = !0;
		}
	}
}, S = { class: "webhook-list" }, C = { class: "d-flex align-center ga-3 mb-5" }, w = { class: "text-medium-emphasis mb-0" }, T = { class: "text-end" }, E = { class: "text-end text-no-wrap" };
function D(e, p, m, h, g, _) {
	let v = u("v-spacer"), y = u("v-btn"), b = u("v-progress-linear"), x = u("v-alert"), D = u("v-chip"), O = u("v-table"), k = u("v-container"), A = u("v-card-title"), j = u("v-text-field"), M = u("v-select"), N = u("v-switch"), P = u("v-card-text"), F = u("v-card-actions"), I = u("v-card"), L = u("v-dialog"), R = u("v-snackbar");
	return c(), i("div", S, [
		s(k, {
			fluid: "",
			class: "pa-4 pa-md-6"
		}, {
			default: f(() => [a("div", C, [
				a("p", w, d(e.$gettext("Send signed notifications when published content changes.")), 1),
				s(v),
				s(y, {
					color: "primary",
					onClick: _.openAdd
				}, {
					default: f(() => [o(d(e.$gettext("Add webhook")), 1)]),
					_: 1
				}, 8, ["onClick"])
			]), e.loading ? (c(), n(b, {
				key: 0,
				indeterminate: ""
			})) : e.items.length ? (c(), n(O, { key: 2 }, {
				default: f(() => [a("thead", null, [a("tr", null, [
					a("th", null, d(e.$gettext("Endpoint")), 1),
					a("th", null, d(e.$gettext("Events")), 1),
					a("th", null, d(e.$gettext("Status")), 1),
					a("th", null, d(e.$gettext("Failures")), 1),
					a("th", null, d(e.$gettext("Last error")), 1),
					a("th", T, d(e.$gettext("Actions")), 1)
				])]), a("tbody", null, [(c(!0), i(t, null, l(e.items, (t) => (c(), i("tr", { key: t.id }, [
					a("td", null, d(t.endpoint), 1),
					a("td", null, d(t.events.join(", ")), 1),
					a("td", null, [s(D, {
						color: t.status ? "success" : void 0,
						size: "small"
					}, {
						default: f(() => [o(d(t.status ? e.$gettext("Active") : e.$gettext("Inactive")), 1)]),
						_: 2
					}, 1032, ["color"])]),
					a("td", null, d(t.failures), 1),
					a("td", null, d(_.errorText(t)), 1),
					a("td", E, [
						s(y, {
							variant: "text",
							size: "small",
							onClick: (e) => _.openEdit(t)
						}, {
							default: f(() => [o(d(e.$gettext("Edit")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(y, {
							variant: "text",
							size: "small",
							onClick: (e) => _.openReplace(t)
						}, {
							default: f(() => [o(d(e.$gettext("Replace")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(y, {
							variant: "text",
							size: "small",
							onClick: (e) => _.rotate(t)
						}, {
							default: f(() => [o(d(e.$gettext("Rotate")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(y, {
							variant: "text",
							size: "small",
							color: "error",
							onClick: (e) => _.remove(t)
						}, {
							default: f(() => [o(d(e.$gettext("Delete")), 1)]),
							_: 1
						}, 8, ["onClick"])
					])
				]))), 128))])]),
				_: 1
			})) : (c(), n(x, {
				key: 1,
				type: "info",
				variant: "tonal"
			}, {
				default: f(() => [o(d(e.$gettext("No webhooks configured.")), 1)]),
				_: 1
			}))]),
			_: 1
		}),
		s(L, {
			modelValue: e.dialog,
			"onUpdate:modelValue": p[4] ||= (t) => e.dialog = t,
			"max-width": "640"
		}, {
			default: f(() => [s(I, null, {
				default: f(() => [
					s(A, null, {
						default: f(() => [o(d(e.selected ? e.$gettext("Edit webhook") : e.$gettext("Add webhook")), 1)]),
						_: 1
					}),
					s(P, null, {
						default: f(() => [
							e.selected ? r("", !0) : (c(), n(j, {
								key: 0,
								modelValue: e.url,
								"onUpdate:modelValue": p[0] ||= (t) => e.url = t,
								label: e.$gettext("HTTPS endpoint URL"),
								maxlength: "500",
								autofocus: ""
							}, null, 8, ["modelValue", "label"])),
							s(M, {
								modelValue: e.events,
								"onUpdate:modelValue": p[1] ||= (t) => e.events = t,
								items: e.names,
								label: e.$gettext("Events"),
								multiple: "",
								chips: ""
							}, null, 8, [
								"modelValue",
								"items",
								"label"
							]),
							e.selected ? (c(), n(N, {
								key: 1,
								modelValue: e.status,
								"onUpdate:modelValue": p[2] ||= (t) => e.status = t,
								color: "success",
								label: e.$gettext("Active")
							}, null, 8, ["modelValue", "label"])) : (c(), n(x, {
								key: 2,
								type: "info",
								variant: "tonal"
							}, {
								default: f(() => [o(d(e.$gettext("New webhooks are inactive until you save them as active.")), 1)]),
								_: 1
							}))
						]),
						_: 1
					}),
					s(F, null, {
						default: f(() => [
							s(v),
							s(y, { onClick: p[3] ||= (t) => e.dialog = !1 }, {
								default: f(() => [o(d(e.$gettext("Cancel")), 1)]),
								_: 1
							}),
							s(y, {
								color: "primary",
								loading: e.saving,
								onClick: _.save
							}, {
								default: f(() => [o(d(e.$gettext("Save")), 1)]),
								_: 1
							}, 8, ["loading", "onClick"])
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s(L, {
			modelValue: e.replaceDialog,
			"onUpdate:modelValue": p[7] ||= (t) => e.replaceDialog = t,
			"max-width": "640"
		}, {
			default: f(() => [s(I, null, {
				default: f(() => [
					s(A, null, {
						default: f(() => [o(d(e.$gettext("Replace webhook destination")), 1)]),
						_: 1
					}),
					s(P, null, {
						default: f(() => [s(j, {
							modelValue: e.url,
							"onUpdate:modelValue": p[5] ||= (t) => e.url = t,
							label: e.$gettext("HTTPS endpoint URL"),
							maxlength: "500",
							autofocus: ""
						}, null, 8, ["modelValue", "label"]), s(x, {
							type: "warning",
							variant: "tonal"
						}, {
							default: f(() => [o(d(e.$gettext("Replacing the destination rotates the secret and disables the webhook.")), 1)]),
							_: 1
						})]),
						_: 1
					}),
					s(F, null, {
						default: f(() => [
							s(v),
							s(y, { onClick: p[6] ||= (t) => e.replaceDialog = !1 }, {
								default: f(() => [o(d(e.$gettext("Cancel")), 1)]),
								_: 1
							}),
							s(y, {
								color: "primary",
								loading: e.saving,
								onClick: _.replace
							}, {
								default: f(() => [o(d(e.$gettext("Replace")), 1)]),
								_: 1
							}, 8, ["loading", "onClick"])
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s(L, {
			modelValue: e.secretDialog,
			"onUpdate:modelValue": p[9] ||= (t) => e.secretDialog = t,
			"max-width": "640",
			persistent: ""
		}, {
			default: f(() => [s(I, null, {
				default: f(() => [
					s(A, null, {
						default: f(() => [o(d(e.$gettext("Webhook secret")), 1)]),
						_: 1
					}),
					s(P, null, {
						default: f(() => [s(x, {
							type: "warning",
							variant: "tonal",
							class: "mb-4"
						}, {
							default: f(() => [o(d(e.$gettext("Copy this secret now. It will not be shown again.")), 1)]),
							_: 1
						}), s(j, {
							"model-value": e.secret,
							readonly: ""
						}, null, 8, ["model-value"])]),
						_: 1
					}),
					s(F, null, {
						default: f(() => [
							s(y, {
								color: "primary",
								onClick: _.copySecret
							}, {
								default: f(() => [o(d(e.$gettext("Copy secret")), 1)]),
								_: 1
							}, 8, ["onClick"]),
							s(v),
							s(y, { onClick: p[8] ||= (t) => {
								e.secretDialog = !1, e.secret = "";
							} }, {
								default: f(() => [o(d(e.$gettext("Done")), 1)]),
								_: 1
							})
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s(R, {
			modelValue: e.messageOpen,
			"onUpdate:modelValue": p[10] ||= (t) => e.messageOpen = t,
			color: e.messageColor
		}, {
			default: f(() => [o(d(e.message), 1)]),
			_: 1
		}, 8, ["modelValue", "color"])
	]);
}
var O = /*#__PURE__*/ p(x, [["render", D]]);
//#endregion
export { O as default };
