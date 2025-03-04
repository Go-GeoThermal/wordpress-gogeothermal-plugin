(function () {
    function registerGeoCreditPayment() {
        console.log("🔄 [Geo Credit] Attempting to register Geo Credit Payment method...");

        if (!window.wc || !window.wc.wcBlocksRegistry) {
            console.warn("⚠️ [Geo Credit] WooCommerce Blocks not available, retrying in 500ms...");
            setTimeout(registerGeoCreditPayment, 500);
            return;
        }

        console.log("✅ [Geo Credit] WooCommerce Blocks detected. Proceeding with payment registration...");

        try {
            var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
            var createElement = window.wp.element.createElement;

            registerPaymentMethod({
                name: "geo_credit",
                label: "Credit Payment",
                ariaLabel: "Pay using Go Geothermal credit balance", // Fix added here
                content: createElement("div", {}, "Use your available credit to pay."),
                edit: createElement("div", {}, "Editing Geo Credit Payment"),
                canMakePayment: () => {
                    console.log("🟢 [Geo Credit] Checking canMakePayment()... Returning true.");
                    return true;
                },
                supports: {
                    features: ['products'],
                }
            });
            

            console.log("✅ [Geo Credit] Successfully registered with WooCommerce Blocks.");
        } catch (error) {
            console.error("❌ [Geo Credit] Error registering payment method:", error);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        console.log("🟡 [Geo Credit] DOM Content Loaded. Running registerGeoCreditPayment.");
        registerGeoCreditPayment();
    });

    // Detect if the checkout block is loaded
    document.addEventListener("wc-blocks-checkout-rendered", function () {
        console.log("🟢 [Geo Credit] WooCommerce Blocks Checkout detected! Re-registering.");
        registerGeoCreditPayment();
    });

})();
