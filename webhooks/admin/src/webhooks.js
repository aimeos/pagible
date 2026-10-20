/** @license MIT, https://opensource.org/license/mit */

// maximum number of webhooks the server purges at once, see purgeWebhook in the GraphQL schema
export const PURGE_BATCH = 100;

// default values of the webhook form
export const blank = () => ({ url: "", name: "", events: [], status: false });

/**
 * Translated text of a delivery failure reason, e.g. "Request timed out (504)"
 *
 * @param {Function} $pgettext Translation function of the component
 * @param {String} reason Failure reason reported by the server
 * @param {Number|null} status HTTP status code of the response
 * @returns {String} Translated text
 */
export function reasonText($pgettext, reason, status = null) {
  const reasons = {
    connection_failed: $pgettext("webhooks", "Connection failed"),
    destination_not_allowed: $pgettext("webhooks", "Access denied"),
    invalid_encryption: $pgettext("webhooks", "Secret can't be decrypted, rotate it"),
    invalid_policy: $pgettext("webhooks", "Invalid list of denied networks"),
    invalid_queue: $pgettext("webhooks", "Queue unavailable"),
    invalid_url: $pgettext("webhooks", "Not a valid URL"),
    queue_failed: $pgettext("webhooks", "Queue unavailable"),
    resolution_failed: $pgettext("webhooks", "Host not found"),
    response_headers_too_large: $pgettext("webhooks", "Response too large"),
    timeout: $pgettext("webhooks", "Request timed out"),
  };
  // redirects are rejected to prevent forwarding deliveries to other hosts
  const redirect = reason === "http_error" && status >= 300 && status < 400;
  const text = redirect
    ? $pgettext("webhooks", "Redirects aren't followed")
    : reasons[reason] || $pgettext("webhooks", "Delivery failed");

  return status
    ? $pgettext("webhooks", "%{reason} (%{status})", { reason: text, status })
    : text;
}

// deliveries fail until the secret is rotated
export function undecryptable(item) {
  return item?.last_error?.reason === "invalid_encryption";
}

// only https endpoints with a host name are accepted
export function validUrl(value) {
  const url = (value ?? "").trim();

  if (!/^https:\/\/\S+$/i.test(url)) return false;

  try {
    return !!new URL(url).hostname;
  } catch (_error) {
    return false;
  }
}
