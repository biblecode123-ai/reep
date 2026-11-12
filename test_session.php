<?php
// session_save_path('C:\xampp\tmp'); // Let's see what it is by default in the server context if I don't set it
if (session_start()) {
    echo "Session started successfully.\n";
} else {
    echo "Session failed to start.\n";
}
echo "Save path: " . session_save_path() . "\n";
echo "Is writable: " . (is_writable(session_save_path()) ? 'Yes' : 'No') . "\n";
