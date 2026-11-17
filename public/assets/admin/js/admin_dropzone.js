(function ($) {
  "use strict";

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  function notifyGalleryError(msg) {
    $.notify(
      { message: msg, title: 'Error', icon: 'fa fa-bell' },
      {
        type: 'warning',
        placement: { from: 'top', align: 'right' },
        showProgressbar: true,
        time: 1000,
        delay: 4000
      }
    );
  }

  function stringifyUploadMessage(value) {
    if (value == null || value === '') {
      return '';
    }
    if (typeof value === 'string') {
      return value;
    }
    if (typeof value === 'object') {
      if (value.msg) {
        return String(value.msg);
      }
      if (value.message) {
        return String(value.message);
      }
      if (value.file) {
        return Array.isArray(value.file) ? String(value.file[0]) : String(value.file);
      }
      var keys = Object.keys(value);
      for (var i = 0; i < keys.length; i++) {
        var key = keys[i];
        if (key === 'error' || key === 'status') {
          continue;
        }
        var item = value[key];
        if (Array.isArray(item) && item[0]) {
          return String(item[0]);
        }
        if (typeof item === 'string' && item) {
          return item;
        }
      }
      try {
        return JSON.stringify(value);
      } catch (e) {
        return 'Upload failed.';
      }
    }
    return String(value);
  }

  function messageFromFailedUpload(xhr, fallback) {
    if (xhr && xhr.responseJSON) {
      var fromJson = stringifyUploadMessage(xhr.responseJSON);
      if (fromJson) {
        return fromJson;
      }
    }
    if (xhr && xhr.responseText) {
      try {
        var parsed = JSON.parse(xhr.responseText);
        var fromText = stringifyUploadMessage(parsed);
        if (fromText) {
          return fromText;
        }
      } catch (e) {
        // HTML error pages often contain "Server Error"
        if (/Server Error/i.test(xhr.responseText)) {
          return 'Server could not save the image. Please retry; if it persists contact support (gallery folder permissions).';
        }
      }
    }
    var fromFallback = stringifyUploadMessage(fallback);
    if (fromFallback && /server error/i.test(fromFallback)) {
      return 'Server could not save the image. Please retry; if it persists contact support (gallery folder permissions).';
    }
    return fromFallback || 'Upload failed.';
  }

  // Dropzone initialization
  Dropzone.options.myDropzone = {
    acceptedFiles: '.png, .jpg, .jpeg',
    url: storeUrl,
    success: function (file, response) {
      if (!response) {
        notifyGalleryError('Invalid response from server.');
        this.removeFile(file);
        return;
      }

      if (response.status === 'error') {
        notifyGalleryError(response.msg || 'Upload rejected.');
        this.removeFile(file);
        return;
      }

      if (response.status !== 'success' || !response.file_id) {
        notifyGalleryError('Upload could not be completed.');
        this.removeFile(file);
        return;
      }

      $("#sliders").append(`<input type="hidden" name="slider_images[]" id="slider${response.file_id}" value="${response.file_id}">`);

      // Form ke andar <button> default type="submit" hota hai — is se gallery form POST ho jati thi
      // aur cross se preview / remove kaam nahi karta. type="button" zaroori hai.
      var removeButton = Dropzone.createElement(
        "<button type=\"button\" class=\"rmv-btn\" aria-label=\"Remove image\"><i class=\"fa fa-times\"></i></button>"
      );

      var dz = this;
      removeButton.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        dz.removeFile(file);

        rmvimg(response.file_id);
      });

      file.previewElement.appendChild(removeButton);
    },
    error: function (file, errorMessage, xhr) {
      var msg = messageFromFailedUpload(xhr, errorMessage || 'Upload failed.');
      notifyGalleryError(msg);
      if (file) {
        this.removeFile(file);
      }
    }
  };

  function rmvimg(fileid) {
    var token = $('meta[name="csrf-token"]').attr('content');
    $.ajax({
      url: removeUrl,
      type: 'POST',
      data: {
        fileid: fileid,
        _token: token
      },
      success: function () {
        $("#slider" + fileid).remove();
      },
      error: function (xhr) {
        if (typeof console !== 'undefined' && console.error) {
          console.error('Gallery image remove failed', xhr.status, xhr.responseText);
        }
      }
    });
  }

  //   remove existing images
  $(document).on('click', '.rmvbtndb', function () {
    let indb = $(this).data('indb');
    $(".request-loader").addClass("show");
    $.ajax({
      url: rmvdbUrl,
      type: 'POST',
      data: {
        fileid: indb
      },
      success: function (data) {
        $(".request-loader").removeClass("show");
        var content = {};

        if (data == 'false') {
          $(".request-loader").removeClass("show");
          content.message = "You can't delete all images.!!";
          content.title = 'Warning';
        } else {
          $("#trdb" + indb).remove();
          content.message = 'Slider image deleted successfully!';
          content.title = 'Success';
        }

        content.icon = 'fa fa-bell';

        $.notify(content, {
          type: 'success',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000
        });
      }
    });
  });

  //   load event slider images
  if (loadImgs.length > 0) {
    $.get(loadImgs, function (data) {
      for (var i = 0; i < data.length; i++) {
        let msg = `<tr class="table-row" id="trdb${data[i].id}">
                            <td>
                                <img class="thumb-preview wf-150"
                                src="${baseUrl}/assets/admin/img/event-gallery/${data[i].image} " alt="slider image">
                            </td>
                            <td>
                                <i class="fas fa-times-circle rmvbtndb" data-indb="${data[i].id}"></i>
                            </td>
                        </tr>`;

        $("#img-table").append(msg);
      }
    });
  }

  if (typeof ProductloadImgs !== 'undefined') {
    //   load product slider images
    if (ProductloadImgs.length > 0) {
      $.get(ProductloadImgs, function (data) {
        for (var i = 0; i < data.length; i++) {
          let msg = `<tr class="table-row" id="trdb${data[i].id}">
                                <td>
                                    <img class="thumb-preview wf-150"
                                    src="${baseUrl}/assets/admin/img/product/gallery/${data[i].image} " alt="slider image">
                                </td>
                                <td>
                                    <i class="fas fa-times-circle rmvbtndb" data-indb="${data[i].id}"></i>
                                </td>
                            </tr>`;

          $("#img-table").append(msg);
        }
      });
    }

  }
})(jQuery);
