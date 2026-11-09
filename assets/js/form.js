// The callback reCAPTCHA calls when the visitor passes; it sends the intake form.
window.wp2emmieRecaptchaSubmit = function () {
    document.getElementById('emmie-form').submit();
};

(function () {
    // Contact person toggle
    var checkbox = document.getElementById('wp2emmie-has-contact');
    var fields = document.getElementById('wp2emmie-contact-fields');
    if (checkbox && fields) {
        var toggleContact = function () {
            fields.style.display = checkbox.checked ? 'block' : 'none';
        };
        checkbox.addEventListener('change', toggleContact);
        toggleContact();
    }

    // Referral source "Anders" toggle
    var referralSelect = document.getElementById('wp2emmie-referral-source');
    var referralOther = document.getElementById('wp2emmie-referral-other');
    if (referralSelect && referralOther) {
        var toggleReferralOther = function () {
            referralOther.style.display = referralSelect.value === 'Anders' ? 'inline' : 'none';
        };
        referralSelect.addEventListener('change', toggleReferralOther);
        toggleReferralOther();
    }
})();
