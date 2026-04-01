/**
 * CommunicationUtils.js — shared send-text / send-email utilities.
 *
 * Loaded globally after CRMJSOM.js.  Every page that shows email/text
 * buttons should use these instead of rolling its own platform logic.
 *
 * Usage (from any page JS):
 *   window.CRM.comm.openSms(phones)           — open native SMS app
 *   window.CRM.comm.openMailto(emails)         — open mail client (to:)
 *   window.CRM.comm.openBcc(emails)            — open mail client (bcc:)
 *   window.CRM.comm.copyEmails(csv)            — copy to clipboard
 *   window.CRM.comm.copyPhones(csv)            — copy to clipboard
 *   window.CRM.comm.platform                   — { isIOS, isMac, isAndroid, isWindows }
 */
(function () {
  "use strict";

  // Ensure CRM namespace exists (Header.php creates it, but guard anyway)
  if (!window.CRM) window.CRM = {};

  // ------------------------------------------------------------------ //
  // Platform detection (run once)
  // ------------------------------------------------------------------ //
  var ua = navigator.userAgent || "";
  var platform = {
    isIOS: /iPad|iPhone|iPod/.test(ua) && !window.MSStream,
    isMac: /Macintosh/.test(ua),
    isAndroid: /Android/.test(ua),
    isWindows: /Windows/.test(ua),
  };
  // Refine: iPadOS 13+ reports as Mac but has touch
  if (platform.isMac && "ontouchend" in document) {
    platform.isIOS = true;
  }

  // ------------------------------------------------------------------ //
  // SMS link builder
  // ------------------------------------------------------------------ //

  /**
   * Build a platform-appropriate sms: URI for the given phone numbers.
   *
   * iOS (Safari/WebKit): sms://open?addresses=+1234,+5678&body=
   * Android: sms:+1234,+5678 (comma-separated, RFC 5724)
   * macOS desktop / Windows: sms:+1234 (single only — multi-recipient
   *   not reliably supported on desktop browsers)
   *
   * Returns null when multi-recipient is requested on a desktop platform
   * that doesn't support it — caller should fall back to clipboard copy.
   *
   * @param {string[]} phones — cleaned E.164 or digit-only numbers
   * @returns {string|null} URI or null if not supported for this count/platform
   */
  function buildSmsLink(phones) {
    if (!phones || phones.length === 0) return null;

    // iOS (including iPadOS) — Apple's multi-recipient format
    if (platform.isIOS) {
      return "sms://open?addresses=" + phones.join(",") + "&body=";
    }

    // Android — RFC 5724 comma-separated
    if (platform.isAndroid) {
      return "sms:" + phones.join(",");
    }

    // Desktop (macOS, Windows, Linux) — only single recipient is reliable
    if (phones.length === 1) {
      return "sms:" + phones[0];
    }

    // Multi-recipient on desktop — not supported, return null
    return null;
  }

  /**
   * Clean an array of formatted phone strings to digit-only (keeping leading +).
   * @param {string[]} rawPhones
   * @returns {string[]}
   */
  function cleanPhones(rawPhones) {
    return rawPhones
      .map(function (p) {
        return p.replace(/[^\d+]/g, "");
      })
      .filter(Boolean);
  }

  // ------------------------------------------------------------------ //
  // Public API
  // ------------------------------------------------------------------ //

  // Extend the existing window.CRM.comm object
  var comm = window.CRM.comm || {};

  comm.platform = platform;

  /**
   * Open the native SMS app with the given phone numbers.
   * @param {string[]} phones — raw formatted phone numbers (will be cleaned)
   */
  comm.openSms = function (phones) {
    var cleaned = cleanPhones(phones);
    if (cleaned.length === 0) {
      window.CRM.notify(i18next.t("No phone numbers available"), {
        type: "warning",
        delay: 3000,
      });
      return;
    }
    var link = buildSmsLink(cleaned);
    if (link) {
      window.location.href = link;
    } else {
      // Desktop multi-recipient fallback — copy numbers and notify
      comm.copyPhones(phones.join(", "));
      window.CRM.notify(i18next.t("Multiple recipients not supported on desktop — numbers copied to clipboard"), {
        type: "info",
        delay: 5000,
      });
    }
  };

  /**
   * Open the mail client with a mailto: link.
   * @param {string} emailCsv — comma-separated email addresses
   */
  comm.openMailto = function (emailCsv) {
    if (!emailCsv) {
      window.CRM.notify(i18next.t("No email addresses available"), {
        type: "warning",
        delay: 3000,
      });
      return;
    }
    window.location.href = "mailto:" + encodeURIComponent(emailCsv);
  };

  /**
   * Open the mail client with a BCC mailto: link.
   * @param {string} emailCsv — comma-separated email addresses
   */
  comm.openBcc = function (emailCsv) {
    if (!emailCsv) {
      window.CRM.notify(i18next.t("No email addresses available"), {
        type: "warning",
        delay: 3000,
      });
      return;
    }
    window.location.href = "mailto:?bcc=" + encodeURIComponent(emailCsv);
  };

  /**
   * Copy email addresses to clipboard with toast feedback.
   * @param {string} emailCsv — comma-separated email addresses
   */
  comm.copyEmails = function (emailCsv) {
    return window.CRM.copyToClipboard(emailCsv, i18next.t("Email addresses copied to clipboard"));
  };

  /**
   * Copy phone numbers to clipboard with toast feedback.
   * @param {string} phoneDisplayList — comma-separated formatted phone numbers
   */
  comm.copyPhones = function (phoneDisplayList) {
    return window.CRM.copyToClipboard(phoneDisplayList, i18next.t("Phone numbers copied to clipboard"));
  };

  window.CRM.comm = comm;

  // ------------------------------------------------------------------ //
  // Global delegated handlers for copy buttons
  // ------------------------------------------------------------------ //
  if (window.jQuery) {
    window.jQuery(document).on("click", ".copy-email-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var email = window.jQuery(this).data("email");
      if (email) {
        comm.copyEmails(String(email));
      }
    });

    window.jQuery(document).on("click", ".copy-phone-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var phone = window.jQuery(this).data("phone");
      if (phone) {
        comm.copyPhones(String(phone));
      }
    });
  }
})();
