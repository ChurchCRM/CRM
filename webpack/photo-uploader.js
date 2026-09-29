/**
 * Photo Uploader using Uppy v5
 * Simple, modern photo upload with webcam support
 * Using MODAL mode with proper CSS
 * Converts images to base64 for ChurchCRM API
 */

import Uppy from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import ImageEditor from "@uppy/image-editor";
import Webcam from "@uppy/webcam";
import { escapeHtml } from "./utils/escape-html";

/**
 * Configuration for photo uploader
 * @typedef {Object} PhotoUploaderConfig
 * @property {string} uploadUrl - Upload endpoint URL
 * @property {number} [maxFileSize=5000000] - Maximum file size in bytes (default: 5MB)
 * @property {number} [photoWidth=800] - Target photo width in pixels
 * @property {number} [photoHeight=800] - Target photo height in pixels
 * @property {('1:1'|'free')} [aspectRatio='1:1'] - Crop ratio; 'free' for a banner such as the church logo
 * @property {boolean} [webcam=true] - Offer the webcam tab; false for things that are not a person, such as the church logo
 * @property {string} [title='Upload Photo'] - Heading shown on the Uppy dashboard modal
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
 * Scale an image down to fit within maxWidth x maxHeight, never up. Images already
 * inside the box are returned untouched. JPEG stays JPEG; everything else becomes PNG
 * so transparency survives.
 *
 * @param {Blob} blob - The picked or cropped image
 * @param {number} maxWidth
 * @param {number} maxHeight
 * @returns {Promise<Blob>}
 */
function shrinkToBox(blob, maxWidth, maxHeight) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      const scale = Math.min(1, maxWidth / img.naturalWidth, maxHeight / img.naturalHeight);
      if (scale === 1) {
        resolve(blob);
        return;
      }
      const canvas = document.createElement("canvas");
      canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
      canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
      const context = canvas.getContext("2d");
      context.imageSmoothingQuality = "high";
      context.drawImage(img, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(
        (shrunk) => (shrunk ? resolve(shrunk) : reject(new Error("Could not resize the image"))),
        blob.type === "image/jpeg" ? "image/jpeg" : "image/png",
        0.9,
      );
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("This browser cannot open that image. Choose a JPEG or PNG."));
    };
    img.src = url;
  });
}

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

  // The picture is shrunk to the stored size in the browser before it is sent, so the
  // picked file only has to open in a browser; a 48 MP phone JPEG is 10-20 MB. The
  // server limit applies to the shrunk payload below.
  const MAX_SOURCE_FILE_BYTES = 50 * 1024 * 1024;
  const displayMaxSizeMB = Math.round(MAX_SOURCE_FILE_BYTES / (1024 * 1024));

  // Base64 encoding inflates file size by ~33% (4/3 ratio). Reserve a small fixed safety
  // buffer for the data URI prefix, JSON wrapper bytes, and base64 padding so the encoded
  // POST body stays safely within PHP's post_max_size.
  const UPLOAD_BODY_OVERHEAD_BYTES = 4096;
  const maxPayloadBytes = Math.max(0, Math.floor(maxFileSizeBytes * 0.75) - UPLOAD_BODY_OVERHEAD_BYTES);

  const photoWidth = typeof config.photoWidth === "string" ? parseInt(config.photoWidth, 10) : config.photoWidth || 800;

  const photoHeight =
    typeof config.photoHeight === "string" ? parseInt(config.photoHeight, 10) : config.photoHeight || 800;

  const freeCrop = config.aspectRatio === "free";
  const allowWebcam = config.webcam !== false;
  const dashboardTitle = config.title || "Upload Photo";

  const uppy = new Uppy({
    id: "photo-uploader",
    autoProceed: false,
    restrictions: {
      maxNumberOfFiles: 1,
      maxFileSize: MAX_SOURCE_FILE_BYTES,
      allowedFileTypes: ["image/*"],
    },
  }).use(Dashboard, {
    inline: false, // Use modal mode
    trigger: null, // Don't auto-bind to a trigger
    proudlyDisplayPoweredByUppy: false,
    note: `Max file size: ${displayMaxSizeMB}MB`,
    // The default JPEG thumbnail has no alpha, so a transparent logo previews on black.
    thumbnailType: "image/png",
    closeModalOnClickOutside: true,
    autoOpen: "imageEditor",
    locale: {
      strings: {
        dashboardWindowTitle: dashboardTitle,
        dashboardTitle: dashboardTitle,
      },
    },
  });
  if (allowWebcam) {
    uppy.use(Webcam, {
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
  }

  uppy.use(ImageEditor, {
    quality: 0.9,
    cropperOptions: {
      viewMode: 1,
      // NaN is cropperjs's "free" ratio
      aspectRatio: freeCrop ? Number.NaN : 1,
      autoCropArea: 1,
      responsive: true,
      croppedCanvasOptions: { maxWidth: photoWidth, maxHeight: photoHeight, imageSmoothingQuality: "high" },
    },
    actions: {
      revert: true,
      rotate: true,
      flip: true,
      zoomIn: true,
      zoomOut: true,
      cropSquare: true,
      cropWidescreen: freeCrop,
      cropWidescreenVertical: freeCrop,
    },
  });

  // Enforce the crop ratio every time the editor opens (including after cancel + re-edit).
  // resetEditorState() resets plugin state to aspectRatio:'free' on each start, which
  // causes cropperjs and the UI to fall out of sync. Calling setAspectRatio() via
  // rAF (after initCropper runs in componentDidMount) keeps both in sync.
  uppy.on("file-editor:start", () => {
    const editor = uppy.getPlugin("ImageEditor");
    if (!editor) return;
    const enforce = () => {
      if (editor.cropper) {
        editor.setAspectRatio(freeCrop ? "free" : "1:1");
      } else {
        requestAnimationFrame(enforce);
      }
    };
    requestAnimationFrame(enforce);
  });

  // Uppy's Save calls cropper.getCroppedCanvas(), which is null until cropperjs has
  // decoded the image, and then reads .width from it. A large phone photo stays "not
  // ready" for seconds, so Save has to wait for it.
  const cropperIsReady = () => Boolean(uppy.getPlugin("ImageEditor")?.getPluginState().cropperReady);
  const syncSaveButton = () =>
    requestAnimationFrame(() => {
      const saveButton = document.querySelector(".uppy-DashboardContent-save");
      if (saveButton) saveButton.disabled = !cropperIsReady();
    });
  uppy.on("state-update", syncSaveButton);
  uppy.on("file-editor:start", syncSaveButton);
  document.addEventListener(
    "click",
    (event) => {
      if (event.target.closest?.(".uppy-DashboardContent-save") && !cropperIsReady()) {
        event.stopPropagation();
        event.preventDefault();
      }
    },
    true,
  );

  // Handle all restriction failures (size, type, count) — use Uppy's own message so
  // the persistent alert accurately describes the actual failure reason.
  uppy.on("restriction-failed", (_file, error) => {
    const message =
      error && typeof error.message === "string" && error.message.trim().length > 0
        ? error.message
        : `File size exceeds maximum of ${displayMaxSizeMB}MB. Please select a smaller file.`;
    showPersistentError(message);
  });

  // Custom upload handler that converts image to base64
  uppy.on("upload", (_data) => {
    // Clear any previous persistent errors when a new upload starts
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

    reader.onload = (e) => {
      // v5+: file.data is now nullable, so check e.target.result
      const base64 = e.target?.result;
      if (!base64 || typeof base64 !== "string") {
        const error = new Error("Failed to read file: invalid data");
        console.error("FileReader error:", error);
        showPersistentError(error.message);
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
            // Parse error JSON first; only fall back to statusText if parsing fails
            return response
              .json()
              .then((errorData) => {
                throw new Error(errorData.message || `Upload failed: ${response.statusText}`);
              })
              .catch((parseError) => {
                if (parseError instanceof SyntaxError) {
                  throw new Error(`Upload failed: ${response.statusText}`);
                }
                throw parseError;
              });
          }
          return response.json();
        })
        .then((data) => {
          // v5+: Use 'complete' field to indicate upload completion
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

    reader.onerror = (error) => {
      const fileError = new Error("Failed to read file");
      console.error("FileReader error:", error || fileError);
      showPersistentError(fileError.message);
      uppy.emit("upload-error", file, fileError);
      uppy.emit("complete", { successful: [], failed: [file] });
    };

    // v5+: file.data is nullable for remote files, check existence
    if (file.data == null) {
      const error = new Error("File data is not available");
      console.error(error.message);
      showPersistentError(error.message);
      uppy.emit("upload-error", file, error);
      uppy.emit("complete", { successful: [], failed: [file] });
      return;
    }

    shrinkToBox(file.data, photoWidth, photoHeight)
      .then((shrunk) => {
        if (shrunk.size > maxPayloadBytes) {
          throw new Error(
            `The resized image is larger than the server limit of ${(maxPayloadBytes / (1024 * 1024)).toFixed(1)}MB.`,
          );
        }
        reader.readAsDataURL(shrunk);
      })
      .catch((error) => {
        console.error("Resize error:", error.message);
        showPersistentError(error.message);
        uppy.emit("upload-error", file, error);
        uppy.emit("complete", { successful: [], failed: [file] });
      });
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
 * Show a persistent, non-auto-dismissing error alert in the top-right corner.
 * Stays visible until the user closes it or starts a new upload.
 * @param {string} message
 */
function showPersistentError(message) {
  clearPersistentError();

  let errorContainer = document.getElementById("uppy-error-container");
  if (!errorContainer) {
    errorContainer = document.createElement("div");
    errorContainer.id = "uppy-error-container";
    errorContainer.style.cssText =
      "position:fixed;top:20px;right:20px;z-index:10000;max-width:400px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;";
    document.body.appendChild(errorContainer);
  }

  // Inject slide-in animation once
  if (!document.getElementById("uppy-error-animation")) {
    const style = document.createElement("style");
    style.id = "uppy-error-animation";
    style.textContent =
      "@keyframes uppySlideIn{from{transform:translateX(500px);opacity:0}to{transform:translateX(0);opacity:1}}";
    document.head.appendChild(style);
  }

  const alertDiv = document.createElement("div");
  alertDiv.className = "alert alert-danger alert-dismissible fade show";
  alertDiv.role = "alert";
  alertDiv.style.cssText =
    "margin:0;padding:12px 16px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);animation:uppySlideIn .3s ease-out;";
  alertDiv.innerHTML = `<strong>Upload Error</strong><p style="margin:4px 0 0;font-size:.95em;">${escapeHtml(message)}</p><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;

  // No auto-dismiss — user must close manually or start a new upload
  errorContainer.appendChild(alertDiv);
}

function clearPersistentError() {
  const el = document.getElementById("uppy-error-container");
  if (el) el.innerHTML = "";
}
