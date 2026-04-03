/**
 * Photo Uploader using Uppy v5
 * Simple, modern photo upload with webcam support
 * Using MODAL mode with proper CSS
 * Converts images to base64 for ChurchCRM API
 */

import Uppy from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import Webcam from "@uppy/webcam";

/**
 * Configuration for photo uploader
 * @typedef {Object} PhotoUploaderConfig
 * @property {string} uploadUrl - Upload endpoint URL
 * @property {number} [maxFileSize=5000000] - Maximum file size in bytes (default: 5MB)
 * @property {number} [photoWidth=800] - Target photo width in pixels
 * @property {number} [photoHeight=800] - Target photo height in pixels
 * @property {Function} [onComplete] - Callback function(result) after successful upload
 */

/**
 * Photo uploader wrapper with modal control methods
 * @typedef {Object} PhotoUploaderInstance
 * @property {Uppy} uppy - The underlying Uppy instance
 * @property {Function} show - Open the upload modal
 * @property {Function} hide - Close the upload modal
 */

/**
 * Create a photo uploader instance with modal dashboard
 *
 * @param {PhotoUploaderConfig} config - Configuration options
 * @returns {PhotoUploaderInstance} - Photo uploader wrapper with show/hide methods
 */
export function createPhotoUploader(config) {
  // Ensure numeric config values are numbers (may come as strings from PHP)
  const maxFileSizeBytes =
    typeof config.maxFileSize === "string" ? parseInt(config.maxFileSize, 10) : config.maxFileSize || 5000000;

  // Base64 encoding inflates file size by exactly 33% (4 bytes output per 3 bytes input,
  // i.e. a 4/3 ratio). Use 0.75 (inverse of 4/3) so the encoded JSON body stays within
  // PHP's post_max_size when the file is converted before being sent to the API.
  const effectiveMaxFileSizeBytes = Math.floor(maxFileSizeBytes * 0.75);
  const displayMaxSizeMB = (effectiveMaxFileSizeBytes / (1024 * 1024)).toFixed(1); // Match Uppy's binary MB display (MiB)

  const photoWidth = typeof config.photoWidth === "string" ? parseInt(config.photoWidth, 10) : config.photoWidth || 800;

  const photoHeight =
    typeof config.photoHeight === "string" ? parseInt(config.photoHeight, 10) : config.photoHeight || 800;

  const uppy = new Uppy({
    id: "photo-uploader",
    autoProceed: false,
    restrictions: {
      maxNumberOfFiles: 1,
      maxFileSize: effectiveMaxFileSizeBytes,
      allowedFileTypes: ["image/*"],
    },
  })
    .use(Dashboard, {
      inline: false, // Use modal mode
      trigger: null, // Don't auto-bind to a trigger
      proudlyDisplayPoweredByUppy: false,
      note: `Max file size: ${displayMaxSizeMB}MB`,
      closeModalOnClickOutside: true,
      locale: {
        strings: {
          dashboardWindowTitle: "Upload Photo",
          dashboardTitle: "Upload Photo",
        },
      },
    })
    .use(Webcam, {
      countdown: false,
      modes: ["picture"],
      mirror: true,
      videoConstraints: {
        facingMode: "user",
        width: { ideal: photoWidth },
        height: { ideal: photoHeight },
      },
      preferredImageMimeType: "image/jpeg",
    });

  // Handle file validation errors (size, type, etc) - show persistent error
  uppy.on("restriction-failed", (file, error) => {
    console.warn("File restriction failed:", error.message);
    // Show error in Uppy's built-in notification (will auto-dismiss)
    // But we'll also display it persistently below the modal
    showPersistentError(`File size exceeds maximum of ${displayMaxSizeMB}MB. Please select a smaller file.`);
  });

  // Custom upload handler that converts image to base64
  uppy.on("upload", (data) => {
    // Clear any previous persistent errors when upload starts
    clearPersistentError();

    // Get all files
    const files = Object.values(uppy.getState().files);
    if (!files || files.length === 0) {
      console.error("No files to upload");
      return;
    }

    const file = files[0];

    // v5+: Use 'complete' field instead of 'uploadComplete'
    uppy.setFileState(file.id, {
      progress: { uploadStarted: Date.now(), complete: false, percentage: 0 },
    });

    const reader = new FileReader();

    reader.onload = function (e) {
      // v5+: file.data is now nullable, so check e.target.result
      const base64 = e.target?.result;
      if (!base64 || typeof base64 !== "string") {
        const error = new Error("Failed to read file: invalid data");
        console.error("FileReader error:", error);
        showPersistentError("Failed to read the selected file. Please try again.");
        uppy.emit("upload-error", file, error);
        uppy.emit("complete", { successful: [], failed: [file] });
        return;
      }

      // Send base64 image to API (backend now handles format detection)
      fetch(config.uploadUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "include",
        body: JSON.stringify({ imgBase64: base64 }),
      })
        .then((response) => {
          if (!response.ok) {
            // Try to parse error response, with proper fallback
            return response
              .json()
              .then((errorData) => {
                const message = errorData.message || `Upload failed: ${response.statusText}`;
                throw new Error(message);
              })
              .catch((parseError) => {
                // If JSON parsing fails, use the statusText
                throw new Error(`Upload failed: ${response.statusText}`);
              });
          }
          return response.json();
        })
        .then((data) => {
          // v5+: Use 'complete' field to indicate upload completion
          // If there are post-processing steps, set to true when post-processing is done
          uppy.setFileState(file.id, {
            progress: { complete: true, percentage: 100 },
            uploadURL: config.uploadUrl,
            response: { body: data },
          });

          uppy.emit("upload-success", file, { body: data });
          uppy.emit("complete", { successful: [file], failed: [] });
        })
        .catch((error) => {
          // v5+: Proper error type handling with meaningful messages
          const uploadError = error instanceof Error ? error : new Error(String(error));
          console.error("Upload error:", uploadError.message);
          showPersistentError(uploadError.message);
          uppy.emit("upload-error", file, uploadError);
          uppy.emit("complete", { successful: [], failed: [file] });
        });
    };

    reader.onerror = function (error) {
      // v5+: file.data is now nullable - check before using
      const fileError = new Error("Failed to read file");
      console.error("FileReader error:", error || fileError);
      showPersistentError("Failed to read the selected file. Please try again.");
      uppy.emit("upload-error", file, fileError);
      uppy.emit("complete", { successful: [], failed: [file] });
    };

    // v5+: file.data is nullable for remote files, check existence
    if (file.data == null) {
      const error = new Error("File data is not available");
      console.error(error.message);
      showPersistentError("File data is not available. Please select a file again.");
      uppy.emit("upload-error", file, error);
      uppy.emit("complete", { successful: [], failed: [file] });
      return;
    }

    reader.readAsDataURL(file.data);
  });

  // Handle upload completion
  uppy.on("complete", (result) => {
    if (result.successful && result.successful.length > 0) {
      if (config.onComplete && typeof config.onComplete === "function") {
        setTimeout(() => config.onComplete(result), 500);
      }
    }
  });

  // v5+: getPlugin now returns proper typed instances (Dashboard in this case)
  // IDE will recognize this as Dashboard plugin with full type support
  const dashboard = uppy.getPlugin("Dashboard");
  if (!dashboard) {
    throw new Error("Dashboard plugin not found (should never happen)");
  }

  // Return wrapper with show/hide methods
  /** @type {PhotoUploaderInstance} */
  return {
    uppy: uppy,
    show: () => {
      clearPersistentError();
      dashboard.openModal();
    },
    hide: () => dashboard.closeModal(),
  };
}

/**
 * Show a persistent error message near the Uppy modal
 * @param {string} message - Error message to display
 */
function showPersistentError(message) {
  clearPersistentError();

  // Create error container if it doesn't exist
  let errorContainer = document.getElementById("uppy-error-container");
  if (!errorContainer) {
    errorContainer = document.createElement("div");
    errorContainer.id = "uppy-error-container";
    errorContainer.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 10000;
      max-width: 400px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    `;
    document.body.appendChild(errorContainer);
  }

  // Create error alert
  const alertDiv = document.createElement("div");
  alertDiv.className = "alert alert-danger alert-dismissible fade show";
  alertDiv.role = "alert";
  alertDiv.style.cssText = `
    margin: 0;
    padding: 12px 16px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease-out;
  `;

  // Add animation
  if (!document.getElementById("uppy-error-animation")) {
    const style = document.createElement("style");
    style.id = "uppy-error-animation";
    style.textContent = `
      @keyframes slideIn {
        from {
          transform: translateX(500px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
    `;
    document.head.appendChild(style);
  }

  alertDiv.innerHTML = `
    <strong>Upload Error</strong>
    <p style="margin-bottom: 0; margin-top: 4px; font-size: 0.95em;">${escapeHtml(message)}</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;

  // Do NOT auto-dismiss - user must manually close to ensure they read the error
  errorContainer.appendChild(alertDiv);
}

/**
 * Clear any persistent error messages
 */
function clearPersistentError() {
  const errorContainer = document.getElementById("uppy-error-container");
  if (errorContainer) {
    errorContainer.innerHTML = "";
  }
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.replace(/[&<>"']/g, (char) => map[char]);
}
