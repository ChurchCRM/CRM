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
  const uppy = new Uppy({
    id: "photo-uploader",
    autoProceed: false,
    restrictions: {
      maxNumberOfFiles: 1,
      maxFileSize: config.maxFileSize || 5000000, // Default 5MB
      allowedFileTypes: ["image/*"],
    },
  })
    .use(Dashboard, {
      inline: false, // Use modal mode
      trigger: null, // Don't auto-bind to a trigger
      proudlyDisplayPoweredByUppy: false,
      note: `Max file size: ${Math.round((config.maxFileSize || 5000000) / 1000000)}MB`,
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
        width: { ideal: config.photoWidth || 800 },
        height: { ideal: config.photoHeight || 800 },
      },
      preferredImageMimeType: "image/jpeg",
    });

  // Custom upload handler that converts image to base64
  uppy.on("upload", (data) => {
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
            return response
              .json()
              .then((err) => {
                throw new Error(err.message || `Upload failed: ${response.statusText}`);
              })
              .catch(() => {
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
          uppy.emit("upload-error", file, uploadError);
          uppy.emit("complete", { successful: [], failed: [file] });
        });
    };

    reader.onerror = function (error) {
      // v5+: file.data is now nullable - check before using
      const fileError = new Error("Failed to read file");
      console.error("FileReader error:", error || fileError);
      uppy.emit("upload-error", file, fileError);
      uppy.emit("complete", { successful: [], failed: [file] });
    };

    // v5+: file.data is nullable for remote files, check existence
    if (file.data == null) {
      const error = new Error("File data is not available");
      console.error(error.message);
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
    show: () => dashboard.openModal(),
    hide: () => dashboard.closeModal(),
  };
}
