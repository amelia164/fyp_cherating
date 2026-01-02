<?php

// Sanity check route
Route::get('/ping', function () {
    echo "Pong! Routing is working ✅";
});

// ------------------------
// Home / Public Routes
// ------------------------
Route::get('/', 'Home@index');
Route::match(['GET', 'POST'], '/rooms', 'Home@rooms');
Route::get('/about', 'Home@about');
Route::get('/contact', 'Home@contact');
Route::post('/contact-submit', 'Home@handleContactForm');
Route::post('/booking-confirmation', 'Home@bookingConfirmation');
Route::get('/booking-confirmation', 'Home@handleConfirmationView');
Route::post('/confirm-booking', 'Home@confirmBooking');
Route::get('/finalize-booking', 'Home@finalizeBooking');
Route::get('/confirmation-done/{booking_id}', 'Home@confirmationDone');
Route::get('/download-invoice/{booking_id}', 'Home@downloadInvoice');
Route::post('/check-customer-availability', 'Home@checkCustomerAvailability');
Route::get('/booking/cancel/{id}', 'Booking@cancelBooking');
Route::match(['GET', 'POST'], '/chatbot/ask', 'Chatbot@ask');

// Review Routes
Route::get('/get-reviews/{room_id}', 'Home@getReviews');
Route::post('/submit-review', 'Home@submitReview');

// Payment Verification Route
Route::get('/verify-payment/{bill_id}', 'Home@manualVerify');

// Virtual Tour Route
Route::get('/virtual-tour/{room_id}', 'Home@virtualTour');

// ------------------------
// Unified Authentication (Admin + Customer)
// ------------------------
Route::group('/auth', function () {
    Route::get('/login', 'Auth@login');
    Route::post('/login', 'Auth@authenticate');
    Route::get('/register', 'Auth@register');
    Route::post('/register', 'Auth@registerProcess'); 
    Route::get('/logout', 'Auth@logout');
    Route::get('/verify', 'Auth@verify');
    Route::post('/verify', 'Auth@verify');
    // forgot & reset password
    Route::get('/forgot-password', 'Auth@forgotPassword');
    Route::post('/forgot-password', 'Auth@forgotPasswordProcess');
    Route::get('/reset-password', 'Auth@resetPassword');
    Route::post('/reset-password', 'Auth@resetPasswordProcess');
});
Route::get('/dashboard', 'Auth@dashboard');
Route::get('/profile', 'Auth@profile');
Route::post('/updateProfile', 'Auth@updateProfile');

// ------------------------
// Admin Routes
// ------------------------
Route::group('/admin', function () {
    // Rooms
    Route::get('/rooms', 'Room@index');
    Route::match(['GET', 'POST'], '/rooms/create', 'Room@createOrUpdate');
    Route::match(['GET', 'POST'], '/rooms/edit/{id}', 'Room@createOrUpdate');
    Route::get('/room/view/{id}', 'Room@view');
    Route::get('/rooms/delete/{id}', 'Room@delete');

    // Bookings
    Route::get('/bookings', 'Booking@index');
    Route::match(['GET', 'POST'], '/bookings/create', 'Booking@createOrUpdate');
    Route::match(['GET', 'POST'], '/bookings/edit/{id}', 'Booking@createOrUpdate');
    Route::get('/bookings/view/{id}', 'Booking@viewBooking');
    Route::get('/bookings/delete/{id}', 'Booking@delete');

    // Payments
    Route::get('/payments', 'Payment@index');
    Route::match(['GET', 'POST'], '/payments/create', 'Payment@createOrUpdate');
    Route::match(['GET', 'POST'], '/payments/verify/{id}', 'Payment@verifyPayment');
    Route::post('/payments/update_status', 'Payment@updateStatus');

    // 360 Room Virtual
    Route::get('/room-virtuals', 'RoomVirtual@index');
    Route::match(['GET', 'POST'], '/room-virtuals/create', 'RoomVirtual@createOrUpdate');
    Route::match(['GET', 'POST'], '/room-virtuals/edit/{id}', 'RoomVirtual@createOrUpdate');
    Route::get('/room-virtuals/view/{id}', 'RoomVirtual@viewRoomVirtual');

    // Messages
    Route::get('/messages', 'Message@index');
    Route::get('/messages/view/{id}', 'Message@viewMessage');
    Route::get('/messages/delete/{id}', 'Message@delete');

    // Dashboard Analytics
    Route::match(['GET', 'POST'], '/getUnpaidBookingsJson', 'Admin@getUnpaidBookingsJson');

    // Global Notification
    Route::match(['GET', 'POST'], '/getGlobalNotifications', 'Admin@getGlobalNotifications');
});