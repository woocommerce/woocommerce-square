sequenceDiagram
    title: Square Payment Gateway - Backend Performance Logging Points

    WC->>Payment_Gateway: process_payment()

    Note over Payment_Gateway,WC: Start: process_payment
    
    alt Has Subscription
        Note over Payment_Gateway,Payment_Gateway_Integration: Start: force_tokenization
        Payment_Gateway->>Payment_Gateway_Integration: maybe_force_tokenization()
        Payment_Gateway_Integration-->>Payment_Gateway: Token Response
        Note over Payment_Gateway,Payment_Gateway_Integration: End: force_tokenization
    end
    
    Note over Payment_Gateway,Square_API: Start: create_order
    Payment_Gateway->>Square_API: create_order()
    Square_API-->>Payment_Gateway: Square Order
    Note over Payment_Gateway,Square_API: End: create_order
    
    alt Is Subscription
        Note over Payment_Gateway,Payment_Gateway_Integration: Start: process_change_payment
        Payment_Gateway_Integration->>Payment_Gateway: process_change_payment()
        alt No Existing Token
            Payment_Gateway->>Square_API: create_token()
            Square_API-->>Payment_Gateway: Token Response
        end
        Payment_Gateway-->>Payment_Gateway_Integration: Process Complete
        Note over Payment_Gateway,Payment_Gateway_Integration: End: process_change_payment
    else Regular Payment
        Note over Payment_Gateway,Square_API: Start: payment_transaction
        Payment_Gateway->>Square_API: do_payment_method_transaction()
        Square_API-->>Payment_Gateway: Payment Response
        Note over Payment_Gateway,Square_API: End: payment_transaction
    end
    
    Note over Payment_Gateway: Start: handle_payment_response
    Payment_Gateway->>Payment_Gateway: handle_single_payment_method()
    Payment_Gateway->>Payment_Gateway: handle_multi_payment_methods()
    Note over Payment_Gateway: End: handle_payment_response
    
    Payment_Gateway->>WC: Update Order Status
    Note over Payment_Gateway,WC: End: process_payment
    
    WC-->>Customer: Order Confirmation
