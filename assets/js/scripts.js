wp.utils = wp.utils || {};
wp.epayLinks = wp.epayLinks || {};

wp.utils.clipboard = (text) => {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      alert("Copiado al portapapeles");
    })
    .catch((err) => {
      console.error("Error al copiar al portapapeles:", err);
    });
};

wp.utils.amountFormat = ({amount = 0, showCurrency = true, symbol = 'Q'}) => {
  const value = parseFloat(amount);
  const amountFixed = value.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
  if (showCurrency) {
    return `${symbol} ${amountFixed}`;
  }

  return amountFixed;
};

wp.utils.i18n = wp.utils.i18n || {
  transaction_id: "Transacción Id",
  product_name: "Nombre",
  amount: "Monto",
  installments: "Cuotas",
  payment_link: "Link de pago",
  audit_number: "Correlativo",
  status: "Estado",
  disabled: "Deshabilitado",
  created_at: "Fecha/hora creado",
  updated_at: "Fecha/hora actualizado",
  last_digits: "Número tarjeta",
  cardholder_name: "Nombre de tarjeta",
  authorization_number: "Número de autorización",
  transaction_date: "Fecha/hora transacción",
  approved_at: "Fecha/hora aprobado",
  notes: "Notas"
};

wp.utils.renderDetails = (details) => {
  let detailTemplate = "";

  /**
   * Splits a given text by the "|" character and returns the HTML of an unordered list (<ul>)
   * as a string.
   *
   * @param {string} text - The text to be split and rendered as a list.
   * @returns {string} The HTML string of the generated <ul> with its <li> elements.
   */
  const renderTextToList = (text) => {
    // Split the text by the "|" character
    const parts = text.split("|");

    // Create the <ul> element
    const ul = document.createElement("ul");

    // Iterate over each part and create an <li> for each
    parts.forEach((part) => {
      const li = document.createElement("li");
      li.textContent = part.trim(); // Trim any unnecessary whitespace
      ul.appendChild(li);
    });

    return ul.outerHTML;
  };

  const processValue = ({ key, value = null }) => {
    const tagList = {
      rejected: "danger",
      refunded: "warning",
      error: "danger",
      paid: "success",
      expired: "light",
      pending: "info",
      disabled: "light",
    };

    const linkStates = {
      disabled: "Deshabilitado",
      refunded: "Anulado",
      rejected: "Rechazado",
      expired: "Expirado",
      pending: "Pendiente",
      error: "Error",
      paid: "Pagado",
    };

    switch (key) {
      case "authorization_number":
        return value ? `<code>${value}</code>` : "—";
      case "last_digits":
        return `···· ···· ···· ${value || "····"}`;
      case "payment_link":
        return `<a href="${value}" target="_blank">${value}</a>`;
      case "amount":
        return `${wp.utils.amountFormat({ amount: value })}`;
      case "disabled":
        return value == 1 ? "✅" : "—";
      case "status":
        return `<span class="badge badge-pill badge-${tagList[value]}">
        ${linkStates[value]}
        </span>`;
      case "notes":
        return value ? renderTextToList(value) : "—";
      default:
        return value || "—";
    }
  };

  const renderRow = ({ key, value }) => {
    let row = "";

    row += `<div class="row-detail">`;
    row += `<dt>${wp.utils.i18n[key] || key}: </dt>`;
    row += `<dd>${value}</dd>`;
    row += `</div>`;

    return row;
  };

  for (let key in details) {
    if (
      key == "id" ||
      key == "transaction_response" ||
      key == "auditNumber" ||
      key == "deleted_at" ||
      key == "request" ||
      key == "notes"
    ) {
      continue;
    }

    let value = processValue({ key, value: details[key] });

    detailTemplate += renderRow({ key, value });
  }

  if (details.notes) {
    notes = details.notes ? renderTextToList(details.notes) : "—";
    detailTemplate += renderRow({ key: "notes", value: notes });
  }

  if (details.transaction_response) {
    responseData = JSON.parse(details.transaction_response);
    // Obtiene las claves del objeto
    const keys = Object.keys(responseData);

    // Selecciona la última clave
    const lastKey = keys[keys.length - 1];

    // Accede al último elemento del objeto
    const lastElement = responseData[lastKey];
    detailTemplate += "<hr>";
    detailTemplate += `<h4 class="text-center">Última respuesta ePayServer</h4>`;

    detailTemplate += wp.utils.renderDetails(lastElement);
  }

  return detailTemplate;
};

wp.linksData = JSON.parse(document.getElementById("data-links").textContent);

jQuery(document).ready(function ($) {
  $("#links-table").delegate(".copy-link", "click", function (event) {
    const { target } = event;
    const { dataset } = target;

    wp.utils.clipboard(dataset.link);
  });

  $(".copy-link-code").click(function (event) {
    const { dataset } = event.target;
    wp.utils.clipboard(dataset.link);
  });

  $("#tabs ul li a").click(function (e) {
    e.preventDefault();
    $("#tabs ul li a").removeClass("active");
    $(this).addClass("active");
    $("#tabs > div").removeClass("active");
    $($(this).attr("href")).addClass("active");
  });
  // Activar la primera pestaña por defecto
  $("#tabs ul li a:first").click();

  $(".delete-link-button").on("click", function () {
    var linkId = $(this).data("link-id");

    if (confirm("¿Estás seguro de que deseas eliminar este link de pago?")) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "delete_epay_payment_link",
          link_id: linkId,
        },
        success: function (response) {
          if (response.success) {
            alert("El link ha sido eliminado.");
            location.reload();
          } else {
            alert("Hubo un error al eliminar el link.");
          }
        },
      });
    }
  });

  $(".disable-link-button").on("click", function () {
    const linkId = $(this).data("link-id");

    if (
      confirm("¿Estás seguro de que deseas deshabilitar este link de pago?")
    ) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "disable_epay_payment_link",
          link_id: linkId,
        },
        success: function (response) {
          if (response.success) {
            alert("El link ha sido deshabilitado.");
            location.reload();
          } else {
            alert("Hubo un error al deshabilitar el link.");
          }
        },
      });
    }
  });

  const $detailsDialog = $("#transaction-details-modal");
  const detailsDialog = $detailsDialog.dialog({
    autoOpen: false,
    resizable: false,
    height: 600,
    width: 750,
    modal: true,
    buttons: [
      {
        text: "Cerrar",
        class: "button",
        click: function () {
          $(this).dialog("close");
        }
      }
    ]
  });

  $("#links-table").delegate(".showDetails", "click", function() {
    const linkId = $(this).data("link-id");
    const linkData = wp.linksData.find((item) => (item.id * 1) === linkId);
    const content = wp.utils.renderDetails(linkData);

    $("#content-details").html(content);

    detailsDialog.dialog("open");
  });

  const $refundDialog = $("#dialog-refund-form");
  const $refundForm = $("#refund-epay-transaction");
  wp.epayLinks.refundAction = () => {
    const formDataObject = {};
    const formData = new FormData($refundForm[0]);
    formData.forEach((value, key) => {
      formDataObject[key] = value;
    });

    formDataObject.action = "refund_epay_transaction"
    $("#refund-transaction-btn").attr("disabled", "disabled")
      .text("Enviado...");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: formDataObject,
      success: function (response) {
        if (response.success) {
          alert("Transacción realizada con éxito.");
          location.reload();
        } else {
          alert("Hubo un error al eliminar el link.");
        }
      },
    });
  }

  const refundDialog = $refundDialog.dialog({
    autoOpen: false,
    width: 500,
    modal: true,
    buttons: [
      {
        id: "refund-transaction-btn",
        text: "Enviar",
        click: wp.epayLinks.refundAction,
        class: "button button-primary",
      },
      {
        class: "button",
        text: "Cerrar",
        click: function () {
          refundDialog.dialog("close");
        },
      },
    ],
    close: function () {
      $("#refund-transaction-btn").removeAttr("disabled", "disabled");
      $refundForm[0].reset();
      $("#uuid").text("");
      $("#transaction_id", $refundDialog).val();
      $("#name", $refundDialog).text("");
      $("#amount", $refundDialog).text("");
    },
  });

  $refundForm.submit(function(event) {
    event.preventDefault();
    wp.epayLinks.refundAction();
  });

  $(".refund-transaction").on("click", function (event) {
    const linkId = $(this).data("link-id");
    const linkData = wp.linksData.find((item) => item.id * 1 === linkId);

    $("#uuid").text(linkData.transaction_id);
    $("#transaction_id", $refundDialog).val(linkData.id);
    $("#name", $refundDialog).text(linkData.product_name);
    $("#amount", $refundDialog).text(
      wp.utils.amountFormat({ amount: linkData.amount })
    );
    refundDialog.dialog("open");
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("generate-payment-link-form");
  const submitButton = document.getElementById("submit-payment-link");

  form.addEventListener("submit", function () {
    // Desactivar el botón para evitar múltiples envíos
    submitButton.disabled = true;
    submitButton.value = "Generando...";
  });
});
