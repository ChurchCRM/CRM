/**
 * Notification wrapper for Notyf
 * Provides backward compatibility with bootstrap-notify API
 */

import { Notyf } from "notyf";
import "notyf/notyf.min.css";

// Lazy-initialize Notyf after DOM is ready
let notyf = null;

function getNotyf() {
    if (!notyf) {
        notyf = new Notyf({
            duration: 3000,
            position: {
                x: "right",
                y: "top",
            },
            dismissible: true,
            ripple: true,
        });
    }
    return notyf;
}

/**
 * Notification function with backward compatibility for bootstrap-notify API
 * @param {string|object} messageOrObject - Message string or object with message/icon
 * @param {object} options - Notification options (type, delay, placement, etc.)
 */
export function notify(messageOrObject, options = {}) {
    let message = "";

    // Handle both API formats:
    // notify('message', { type: 'success' })
    // notify({ message: 'text', icon: 'fa fa-check' }, { type: 'success' })
    if (typeof messageOrObject === "string") {
        message = messageOrObject;
    } else if (messageOrObject && typeof messageOrObject === "object") {
        message = messageOrObject.message || "";
    }

    // Extract options
    const type = options.type || "info";
    const duration = options.delay || 3000;

    // Get Notyf instance (lazy initialization)
    const notyfInstance = getNotyf();

    // Map bootstrap-notify types to Notyf
    // Using Bootstrap 4.6.2 theme colors for consistency
    if (type === "danger") {
        notyfInstance.error({
            message: message,
            duration: duration,
        });
    } else if (type === "success") {
        notyfInstance.success({
            message: message,
            duration: duration,
        });
    } else if (type === "warning") {
        notyfInstance.open({
            type: "warning",
            message: message,
            duration: duration,
            background: "#ffc107", // Bootstrap 4 $warning
            icon: {
                className: "fa fa-exclamation-triangle",
                tagName: "i",
                color: "#212529", // Dark text for better contrast on yellow
            },
        });
    } else {
        // info or other types
        notyfInstance.open({
            type: "info",
            message: message,
            duration: duration,
            background: "#17a2b8", // Bootstrap 4 $info
            icon: {
                className: "fa fa-info-circle",
                tagName: "i",
                color: "white",
            },
        });
    }
}

// Export Notyf instance getter for advanced usage
export { getNotyf as getNotyfInstance };

// Attach to window.CRM for legacy scripts loaded via <script> tags
if (typeof window !== "undefined") {
    if (!window.CRM) {
        window.CRM = {};
    }
    window.CRM.notify = notify;
    Object.defineProperty(window.CRM, "notyf", {
        get: getNotyf,
    });
}
