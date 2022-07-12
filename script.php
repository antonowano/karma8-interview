<?php

// скрипт для запуска 1 раз в день
// запускать можно с помощью крон-планировщика
//
//      00 10 * * * php /path/to/script.php
//

$from = 'noreply@company.com';
$subject = 'Subscription ends soon';
$body = '{username}, your subscription is expiring soon';

$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=database', 'username', 'password');

// В emails должны быть все почтовые ящики из users иначе, пользователь не будет обработан.
// Письмо отправляется один раз, когда осталось ровно 3 дня
$stmRead = $pdo->prepare('
    SELECT 
        u.username,
        u.email,
        e.checked
    FROM users u
    JOIN emails e ON e.email = u.email
    WHERE u.confirmed = 1 
      AND DATEDIFF(FROM_UNIXTIME(u.validts), NOW()) = 3
      AND ((e.checked = 1 AND e.valid = 1) OR e.checked = 0)
');
$stmRead->execute();
$rows = $stmRead->fetch(PDO::FETCH_ASSOC);

$stmUpdateEmailState = $pdo->prepare('UPDATE emails SET checked = 1, valid = :state WHERE email = :email');

foreach ($rows as $row) {
    // Т.к. из бд выбираем только валидные и непроверенные
    $validEmail = true;

    // Проверяем не проверенные и перезаписываем $validEmail
    if ($row['checked'] == false) {
        $validEmail = check_email($row['email']);
        $stmUpdateEmailState->execute([
            'valid' => $validEmail,
            'email' => $row['email'],
        ]);
    }

    if ($validEmail) {
        $personalBody = str_replace('{username}', $row['username'], $body);
        send_email($row['email'], $from, $row['email'], $subject, $personalBody);
    }
}

function check_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// не совсем понятно зачем функции нужен $email, по логике он указывается в $to
// возможно там полное имя емейла (с именем пользователя), но они успешно генерируются почтовиками
function send_email($email, $from, $to, $subj, $body) {
}
