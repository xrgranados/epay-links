jQuery(document).ready(function ($) {
  $(document).on("change", "#epay_ccNo", function () {
    $("#epay_ccNo").validateCreditCard(function (result) {
      // Card logo
      if (result.card_type !== null) {
        if (result.card_type.name === "visa") {
          $("#epay-card-type").html(
            `<img src="../wp-content/plugins/epay-links/assets/img/visa.png">`
          );
        } else if (result.card_type.name === "mastercard") {
          $("#epay-card-type").html(
            `<img src="../wp-content/plugins/epay-links/assets/img/mastercard.png">`
          );
        }
        // $('#epay-card-type').text(result.card_type.name);
      } else {
        $("#epay-card-type").html("");
      }
      // Success icon
      if (result.valid && result.luhn_valid && result.length_valid) {
        $("#epay-card-success").css("visibility", "visible");
      } else {
        $("#epay-card-success").css("visibility", "");
      }
    });
  });

  // $(document).on("change", "#installment", function () {
  //   if ($(this).is(":checked")) {
  //     $("#installment-block").css("visibility", "visible");
  //   } else {
  //     $("#installment-block").css("visibility", "hidden");
  //   }
  // });
  // $(document).ajaxStop(function () {
  //   $("#installment_period").select2({
  //     placeholder: "Choose period",
  //     allowClear: true,
  //     minimumResultsForSearch: Infinity,
  //   });
  // });

  $("#epay_expdate").inputmask(
    { mask: "99 / 99" },
    { placeholder: "MM / YY" }
  );

  $(document).on("input", "#epay_ccNo", function (e) {
    this.value = this.value.replace(/[^\d]/, "");
  });

  $(document).on("input", "#epay_cvv", function (e) {
    this.value = this.value.replace(/[^\d]/, "");
    if (this.value.length > 3) {
      this.value = this.value.substring(0, 3);
    }
  });
  $(document).on("input", "#cardholder", function (e) {
    this.value = this.value.replace(/[0-9]/, "");
  });

  $(document).on("blur", "#epay_expdate", function (e) {
    var ts = Date.parse(this.value);
    if (this.value !== "" && !this.value.includes("_")) {
      var myarr = this.value.split(" / ");
      if (ts.year > myarr[1] || myarr[0] > 12 || myarr[0] == "00") {
        this.value = "";
      } else if (ts.year == myarr[1]) {
        if (ts.month > myarr[0]) {
          this.value = "";
        }
      }
    } else {
      this.value = "";
    }
  });

  $(document).on("click", "#place_order", function (e) {
    if ($("#payment_method_epay").prop("checked")) {
      if ($("#epay_ccNo").val() === "") {
        e.preventDefault();
        alert("Card Number is invalid.");
        return;
      }
      if ($("#epay_expdate").val() === "") {
        e.preventDefault();
        alert("Expire date is invalid.");
        return;
      }
      if ($("#epay_cvv").val().length < 3 || $("#epay_cvv").val() === "") {
        e.preventDefault();
        alert("CVV is invalid.");
        return;
      }
      if ($("#cardholder").val() === "") {
        e.preventDefault();
        alert("Cardholder is invalid.");
        return;
      }
    }
  });
});
