(function ($) {

  var parameters = {};
  var canvas = {};
  var context = {};
  var inputCache = [];

  var camera = {
    deviceIds: [],
    constraints: {
      video: {
        width: parameters.photoWidth,
        height: parameters.photoHeight,
        frameRate: { ideal: 10, max: 10 }
      }
    }
  };

  var currentImage = {
    image: {},
    height: 0,
    width: 0,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0
  };

  var mouseEvents = {
    offsetX: 0,
    offsetY: 0,
    startX: 0,
    startY: 0,
    isDragging: false,
    prevDiff: 0
  };

  $.fn.PhotoUploader = function (userParameters) {
    parameters = $.extend({}, $.fn.PhotoUploader.defaultParameters, userParameters);
    canvas = $("<canvas>", {
      id: "canvas",
      style: "border: 1px solid black; touch-action: none"
    })
      .attr("width", parameters.photoWidth)
      .attr("height", parameters.photoHeight)
      .on('mousedown', handleMouseDown)
      .on('touchstart', handleMouseDown);

    $(document).on('mousemove', handleMouseMove)
      .on('touchmove', handleMouseMove)
      .on('touchend', handleMouseUp)
      .on('mouseup', handleMouseUp);

    canvasOffset = canvas.offset();
    mouseEvents.offsetX = canvasOffset.left;
    mouseEvents.offsetY = canvasOffset.top;

    context = canvas.get(0).getContext("2d");

    this.append(createModal());

    $("#upload-image").on("hidden.bs.modal", function () {
      stopVideo();
    });

    this.show = function () {
      $("#upload-image").modal("show");
    };
    return this;
  };

  function createHeader() {
    var modalHeader = $("<div>", {
      class: "modal-header"
    });

    modalHeader.append(
      $("<button>", {
        type: "button",
        class: "close",
        "data-dismiss": "modal",
        "aria-label": "Close"
      }).append(
        $("<span>", {
          "aria-hidden": "true",
          html: "&times;"
        }))).append(
      $("<h4>", {
        class: "modal-title",
        id: "upload-Image-label",
        text: "Upload Photo"
      }));
    return modalHeader;
  }

  function createFileSelect() {
    var fileSelect = $("<div>", {
      class: function () {
        if (canCapture()) {
          return "col-md-6";
        } else {
          return "col-md-12"
        }
      },
      id: "fileSelect"
    }).append(
      $("<input>", {
        style: "display: none",
        type: "file",
        name: "file",
        id: "file",
        size: "50"
      }).change(function (e) {
        fileSelectChanged(e)
      })
    ).append(
      $("<label>", {
        for: "file",
        html: '<i class="fa fa-picture-o" aria-hidden="true"></i><br/>Upload an existing Photo'
      })
    ).append(
      $("<p>", {
        text: "Max photo size: " + parameters.maxPhotoSize
      })
    );
    return fileSelect;
  }

  function createCameraSelect() {
    if (canCapture()) {
      var cameraSelect = $("<div>", {
        class: "col-md-6",
        id: "cameraSelect"
      }).append(
        $("<label>", {
          id: "captureFromWebcam",
          html: '<i class="fa fa-video-camera" aria-hidden="true"></i><br>Capture from Webcam',
        }).click(function () {
          $("#previewPane").hide();
          $("#capturePane").show();
          $("#retake").show();
          startVideo();
          $("#snap").click(function () {
            snapshotVideo();
          })
        })
      );
      return cameraSelect;
    }
    else {
      return null;
    }
  }

  function createCapturePane() {
    var capture = $("<div>", {
      class: "row",
      id: "capturePane",
      style: "display:none; text-align: center"
    }).append(
      $("<video>", {
        id: "video",
        width: parameters.photoWidth,
        height: parameters.photoHeight,
        autoplay: true
      })
    ).append(
      $("<br>")
    ).append(
      createCameraChooser()
    ).append(
      $("<button>", {
        class: "btn btn-primary",
        type: "button",
        id: "snap",
        text: "Snap Photo"
      })
    );
    return capture;
  }

  function createPreviewPane() {
    var capture = $("<div>", {
      class: "col-md-12",
      id: "previewPane",
      style: "display: none; text-align: center"
    }).append(
      canvas
    ).append(
      $("<br>")
    ).append(
      createEditControls()
    ).append(
      $("<br>")
    ).append(
      $("<button>", {
        class: "btn btn-warning",
        type: "button",
        id: "retake",
        style: "display:none",
        text: "Re-Take Photo"
      }).click(function () {
        retakeSnapshot();
      })
    );
    return capture;

  }


  function createCameraChooser() {
    var cameraChooser = $("<button>", {
      class: 'btn btn-default',
      type: 'button',
      id: 'switcher',
      text: 'Switch Camera'
    });

    navigator.mediaDevices.enumerateDevices().then(function (devices) {
      for (var i = 0; i < devices.length; i++) {
        if (devices[i].kind !== 'videoinput') {
          continue;
        }
        camera.deviceIds.push(devices[i].deviceId);
      }

      if (camera.deviceIds.length > 1) {
        camera.selectedDevice = 0;

        cameraChooser.on('click', function () {
          if (camera.selectedDevice === camera.deviceIds.length - 1) {
            camera.selectedDevice = 0;
          } else {
            camera.selectedDevice++;
          }

          camera.constraints.video.deviceId =
            camera.deviceIds[camera.selectedDevice];

          startVideo();
        });

      } else {
        cameraChooser.hide();
      }
    });

    return cameraChooser;
  }

  function createEditControls() {
    var editControls = $("<div>", {
      id: "editControls"
    });

    editControls.append(
      $("<button>", {
        class: "btn",
        type: "button",
        id: "shrink",
        text: "-"
      }).click(function () {
        shrinkImage();
      })
    ).append(
      $("<button>", {
        class: "btn",
        type: "button",
        id: "grow",
        text: "+"
      }).click(function () {
        growImage();
      })
    );

    return editControls;


  }

  function createBody() {
    var modalBody = $("<div>", {
      class: "modal-body"
    });

    var container = $("<div>", {
      class: "container-fluid"
    }).append(
      $("<div>", {class: "row"}).append(
        createFileSelect()
      ).append(
        createCameraSelect()
      )
    ).append(
      $("<div>", {class: "row"}).append(
        $("<div>", {id: "imageArea"})
          .append(createCapturePane())
          .append(createPreviewPane())
      )
    );

    return modalBody.append(container);
  }

  function createFooter() {
    var modalFooter = $("<div>", {
      class: "modal-footer"
    });

    modalFooter.append(
      $("<button>", {
        type: "button",
        class: "btn btn-default",
        "data-dismiss": "modal",
        text: "Close"
      })
    ).append(
      $("<input>", {
        id: "uploadImage",
        type: "submit",
        class: "btn btn-primary",
        "data-dismiss": "modal",
        text: "Upload Image"

      }).click(function (event) {
        parameters.uploadImage(event);
      })
    );

    return modalFooter;
  }

  function createModal() {
    var modal = $("<div>", {
      class: "modal fade",
      id: "upload-image",
      tabindex: "-1",
      role: "dialog",
      "aria-labelledby": "upload-Image-label",
      "aria-hidden": "true"
    });

    var modalDialog = $("<div>", {
      id: "photoUploader-dialog",
      class: "modal-dialog modal-lg"
    });

    var uploadForm = $("<form>", {
      action: "#",
      method: "POST",
      enctype: "multipart/form-data",
      id: "UploadForm"
    });

    var modalContent = $("<div>", {
      class: "modal-content"
    });
    return modal.append(
      modalDialog.append(
        uploadForm.append(
          modalContent.append(
            createHeader()
          ).append(
            createBody()
          ).append(
            createFooter()
          )
        )
      )
    );
  }

  function startVideo() {
    $("#photoOr").show();
    $("#photoCapture").show();
    // Grab elements, create settings, etc.
    this.video = document.getElementById('video');

    if (parameters.fakeVideo) {
      this.video.src = 'http://vjs.zencdn.net/v/oceans.mp4';
      this.video.play();
      return;
    }

    // Get access to the camera!
    navigator.mediaDevices.getUserMedia(camera.constraints)
      .then(function (userCameraStream) {
        this.stream = userCameraStream;
        this.video.src = window.URL.createObjectURL(userCameraStream);
        this.video.play();
      });
  }

  function stopVideo() {
    if (this.stream) {
      this.video.pause();
      this.video.src = '';
      this.stream.getTracks()[0].stop();
    }

  }

  function retakeSnapshot() {
    this.video.play();
    $("#previewPane").hide();
    $("#capturePane").show();
  }

  function snapshotVideo() {
    this.video.pause();
    currentImage.image = this.video;
    currentImage.width = this.video.videoWidth;
    currentImage.height = this.video.videoHeight;
    fitImage();
    calcEdges();
    updateCanvas();
    $("#capturePane").hide();
    $("#previewPane").show();
  }

  function fileSelectChanged(fileSelect) {

    var file = fileSelect.target.files[0];
    fileSelect.target.files = null;
    if (!file.type.match('image.*')) {
      return;
    }

    $("#retake").hide();
    $("#capturePane").hide();
    stopVideo();
    $("#previewPane").show();

    currentImage.image = new Image();
    currentImage.image.onload = function () {
      currentImage.height = currentImage.image.height;
      currentImage.width = currentImage.image.width;
      fitImage();
      calcEdges();
      updateCanvas();
      canvas.show();
    };
    currentImage.image.src = URL.createObjectURL(file);

  }

  function calcEdges() {
    currentImage.right = currentImage.left + currentImage.width;
    currentImage.bottom = currentImage.top + currentImage.height;
  }

  function fitImage() {
    if (currentImage.width > parameters.photoWidth) {
      //if the image is wider than the user asked for
      ar = currentImage.height / currentImage.width;
      currentImage.width = parameters.photoWidth;
      currentImage.height = currentImage.width * ar;
    }
    else if (currentImage.height > parameters.photoHeight) {
      //if the image is taller than the user asked for
      ar = currentImage.width / currentImage.height;
      currentImage.height = parameters.photoHeight;
      currentImage.width = currentImage.height * ar;
    }
  }

  function getInput(e) {
    if (e.type === 'touchstart' || e.type === 'touchmove') {
      var touches = [];
      if (!e.touches) {
        touches = e.originalEvent.touches;

      } else {
        touches = e.touches;
      }

      inputCache = [];
      for (var i = 0; i < touches.length; i++) {
        inputCache.push({
          clientX: touches[i].clientX,
          clientY: touches[i].clientY,
          identifier: touches[i].identifier
        });
      }

      if(inputCache.length == 2) {
        // Calculate the distance between the two pointers
        var diffX = inputCache[0].clientX - inputCache[1].clientX;
        var diffY = inputCache[0].clientY - inputCache[1].clientY;

        // Pythagorean theorem
        mouseEvents.distance = Math.sqrt(diffX * diffX + diffY * diffY);

        if(mouseEvents.isDragging) {
          mouseEvents.prevDistance = mouseEvents.distance;
        }
      }
    } else {
      if (e.type === 'mousedown') {
        inputCache.push({
          clientX: e.clientX,
          clientY: e.clientY,
          identifier: 0
        });
      } else if (mouseEvents.isDragging) {
        inputCache[0].clientX = e.clientX;
        inputCache[0].clientY = e.clientY;
        inputCache[0].identifier = 0;
      }
    }

  }

  function removeInput(e) {
    if (e.type === 'mouseup') {
      inputCache = [];
    }
    // Remove this event from the target's cache
    for (var i = 0; i < inputCache.length; i++) {
      if (inputCache[i].pointerId == e.pointerId) {
        inputCache.splice(i, 1);
        break;
      }
    }
  }

  function handleMouseUp(e) {
    removeInput(e);
    mouseEvents.isDragging = inputCache.length === 1;
  }

  function handleMouseDown(e) {
    getInput(e);
    mouseEvents.isDragging = inputCache.length === 1;

    if (mouseEvents.isDragging) {
      mouseEvents.startX = parseInt(inputCache[0].clientX - mouseEvents.offsetX);
      mouseEvents.startY = parseInt(inputCache[0].clientY - mouseEvents.offsetY);
    }
  }

  function handleMouseMove(e) {
    getInput(e);
    if (mouseEvents.isDragging) {
      dX = parseInt(inputCache[0].clientX) - mouseEvents.startX - mouseEvents.offsetX;
      dY = parseInt(inputCache[0].clientY) - mouseEvents.startY - mouseEvents.offsetY;
      currentImage.top += dY;
      currentImage.bottom += dY;
      currentImage.left += dX;
      currentImage.right += dX;
      mouseEvents.startX = parseInt(inputCache[0].clientX);
      mouseEvents.startY = parseInt(inputCache[0].clientY);
      updateCanvas();

    } else if (inputCache.length == 2) {
      var scale = mouseEvents.distance / mouseEvents.prevDistance;
      if (scale > 0) {
        // The distance between the two pointers has decreased
        currentImage.width *= scale;
        currentImage.height *= scale;

        calcEdges();
        updateCanvas();
      }

      // Cache the distance for the next move event
      mouseEvents.prevDistance = mouseEvents.distance;
    }
  }

  function shrinkImage() {
    currentImage.width *= .90;
    currentImage.height *= .90;
    calcEdges();
    updateCanvas();
  }

  function growImage() {
    currentImage.width *= 1.1;
    currentImage.height *= 1.1;
    calcEdges();
    updateCanvas();
  }

  function updateCanvas() {
    context.clearRect(0, 0, parameters.photoWidth, parameters.photoHeight);
    context.drawImage(currentImage.image, currentImage.left, currentImage.top,
      currentImage.width, currentImage.height);
    context.beginPath();
    context.moveTo(currentImage.left, currentImage.top);
    context.lineTo(currentImage.right, currentImage.top);
    context.lineTo(currentImage.right, currentImage.bottom);
    context.lineTo(currentImage.left, currentImage.bottom);
    context.closePath();
    context.stroke();

  }


  function canCapture() {
    return navigator.mediaDevices && navigator.mediaDevices.getUserMedia &&
      window.location.protocol == "https:"
  }

  $.fn.PhotoUploader.defaultParameters = {
    url: "/photo",
    maxPhotoSize: "2MB",
    photoHeight: 240,
    photoWidth: 320,
    uploadImage: function (event) {
      event.preventDefault();
      var dataURL = canvas.get(0).toDataURL();
      $.ajax({
        method: "POST",
        url: parameters.url,
        data: {
          imgBase64: dataURL
        }
      }).done(function (o) {
        $("#upload-image").modal("hide");
        if (parameters.done) {
          parameters.done(o);
        }
      });
    }
  }

})(jQuery);
