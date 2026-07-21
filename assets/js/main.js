document.addEventListener('DOMContentLoaded', function() {

    // Auto-hide flash messages
    var flashMessages = document.querySelectorAll('.flash-alert');
    flashMessages.forEach(function(msg) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(msg);
            bsAlert.close();
        }, 4000);
    });

    // Format amount inputs
    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        input.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
});
