<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Appointment.php';

use Razorpay\Api\Api;

class PaymentController {
    private $appointmentModel;
    private $razorpay;
    
    public function __construct() {
        $this->appointmentModel = new Appointment();
        
        $keyId = getenv('RAZORPAY_KEY_ID');
        $keySecret = getenv('RAZORPAY_KEY_SECRET');
        
        if (!$keyId || !$keySecret) {
            throw new Exception('Razorpay credentials not configured');
        }
        
        $this->razorpay = new Api($keyId, $keySecret);
    }
    
    public function createOrder() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
        if (empty($data['appointment_id'])) {
            return $this->jsonResponse(false, 'Appointment ID is required', null, 400);
        }
        
        if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            return $this->jsonResponse(false, 'Valid amount is required', null, 400);
        }
        
        // Find appointment
        $appointment = $this->appointmentModel->find($data['appointment_id']);
        if (!$appointment) {
            return $this->jsonResponse(false, 'Appointment not found', null, 404);
        }
        
        // Check if already paid
        if ($appointment['is_paid']) {
            return $this->jsonResponse(false, 'This appointment is already paid for', null, 400);
        }
        
        // Check if appointment is confirmed
        if ($appointment['status'] !== 'confirmed') {
            return $this->jsonResponse(false, 'Appointment must be confirmed before payment', null, 400);
        }
        
        try {
            // Create Razorpay order
            $order = $this->razorpay->order->create([
                'amount' => $data['amount'] * 100, // Convert to paise
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'receipt' => 'receipt_' . $data['appointment_id'],
                'notes' => [
                    'appointment_id' => $data['appointment_id'],
                    'patient_name' => $appointment['patient_name'],
                    'patient_email' => $appointment['patient_email'],
                    'service_type' => $appointment['service_type'],
                    'appointment_date' => $appointment['appointment_date']
                ],
                'payment_capture' => 1
            ]);
            
            // Update appointment with order details
            $this->appointmentModel->update($data['appointment_id'], [
                'amount' => $data['amount'],
                'payment_id' => $order->id
            ]);
            
            return $this->jsonResponse(true, 'Payment order created successfully', [
                'order_id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'key_id' => getenv('RAZORPAY_KEY_ID'),
                'appointment_id' => $data['appointment_id'],
                'patient_name' => $appointment['patient_name'],
                'amount_in_rupees' => $data['amount']
            ]);
            
        } catch (Exception $e) {
            error_log('Razorpay order creation error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Payment error: ' . $e->getMessage(), null, 400);
        }
    }
    
    public function verifyPayment() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $requiredFields = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'appointment_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->jsonResponse(false, "$field is required", null, 400);
            }
        }
        
        // Find appointment
        $appointment = $this->appointmentModel->find($data['appointment_id']);
        if (!$appointment) {
            return $this->jsonResponse(false, 'Appointment not found', null, 404);
        }
        
        // Verify payment signature
        $generatedSignature = hash_hmac('sha256', 
            $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'], 
            getenv('RAZORPAY_KEY_SECRET')
        );
        
        if ($generatedSignature !== $data['razorpay_signature']) {
            return $this->jsonResponse(false, 'Invalid payment signature - payment verification failed', null, 400);
        }
        
        try {
            // Update appointment payment status
            $this->appointmentModel->updatePaymentStatus(
                $data['appointment_id'],
                $data['razorpay_payment_id'],
                true
            );
            
            // Verify with Razorpay API
            $payment = $this->razorpay->payment->fetch($data['razorpay_payment_id']);
            
            return $this->jsonResponse(true, 'Payment verified and confirmed successfully!', [
                'appointment_id' => $appointment['id'],
                'status' => 'confirmed',
                'payment_id' => $data['razorpay_payment_id'],
                'is_paid' => true,
                'patient_name' => $appointment['patient_name'],
                'appointment_date' => $appointment['appointment_date'],
                'payment_details' => [
                    'amount' => $payment->amount / 100,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'method' => $payment->method
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Payment verification error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Payment verification error', null, 400);
        }
    }
    
    public function getPaymentDetails($appointmentId) {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return $this->jsonResponse(false, 'Appointment not found', null, 404);
        }
        
        
        $responseData = [
            'id' => $appointment['id'],
            'amount' => $appointment['amount'],
            'is_paid' => (bool)$appointment['is_paid'],
            'payment_id' => $appointment['payment_id'],
            'status' => $appointment['status'],
            'patient_name' => $appointment['patient_name'],
            'appointment_date' => $appointment['appointment_date']
        ];
        
        return $this->jsonResponse(true, 'Payment details retrieved', $responseData);
    }
    
    public function checkPaymentStatus($appointmentId) {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return $this->jsonResponse(false, 'Appointment not found', null, 404);
        }
        
        return $this->jsonResponse(true, 'Payment status retrieved', [
            'is_paid' => (bool)$appointment['is_paid'],
            'payment_id' => $appointment['payment_id'],
            'amount' => $appointment['amount'],
            'status' => $appointment['status']
        ]);
    }
    
    private function jsonResponse($success, $message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = ['success' => $success, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}