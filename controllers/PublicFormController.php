<?php
/**
 * Handlers for visitor-submitted forms. Each one: CSRF (bootstrap), honeypot,
 * per-IP rate limit, validation, insert via prepared statements, JSON/flash reply.
 */
class PublicFormController
{
    private static function guard(string $form, int $max = 5, int $minutes = 60): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            abort(405);
        }
        if (honeypot_tripped()) {
            respond(true, 'Thank you! Your submission has been received.'); // silently drop bots
        }
        $key = "form:$form:" . client_ip();
        if (rate_limited($key, $max, $minutes)) {
            respond(false, 'You have sent several submissions recently. Please try again a little later.');
        }
        rate_hit($key);
    }

    private static function fail(array $errors): never
    {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Please check the highlighted fields.', 'errors' => $errors], 422);
        }
        set_old($_POST);
        respond(false, reset($errors));
    }

    private static function text(string $key, int $max, bool $required, string $label, array &$errors): ?string
    {
        $v = post($key);
        if ($v === '') {
            if ($required) {
                $errors[$key] = "$label is required.";
            }
            return null;
        }
        if (mb_strlen($v) > $max) {
            $errors[$key] = "$label must be at most $max characters.";
        }
        return $v;
    }

    public static function prayer(): never
    {
        self::guard('prayer');
        $errors = [];
        $data = [
            'name'    => self::text('name', 120, true, 'Your name', $errors),
            'email'   => self::text('email', 190, false, 'Email', $errors),
            'phone'   => self::text('phone', 40, false, 'Phone', $errors),
            'request' => self::text('request', 3000, true, 'Prayer request', $errors),
            'is_public' => post('visibility') === 'public' ? 1 : 0, // explicit opt-in only
            'ip_address' => client_ip(),
        ];
        if ($data['email'] && !valid_email($data['email'])) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($errors) {
            self::fail($errors);
        }
        DB::insert('prayer_requests', $data);
        log_activity('submit', 'prayer_requests', 'New prayer request received (' . ($data['is_public'] ? 'public' : 'private') . ')');
        respond(true, 'Thank you, ' . strtok($data['name'], ' ') . '. Our prayer team will stand with you in faith. 🙏', 'prayer.php', ['reset' => true]);
    }

    public static function testimony(): never
    {
        self::guard('testimony', 3);
        $errors = [];
        $data = [
            'name'      => self::text('name', 120, true, 'Your name', $errors),
            'email'     => self::text('email', 190, false, 'Email', $errors),
            'title'     => self::text('title', 200, false, 'Title', $errors),
            'testimony' => self::text('testimony', 5000, true, 'Testimony', $errors),
            'permission_to_publish' => !empty($_POST['permission_to_publish']) ? 1 : 0,
            'status'    => 'pending',
        ];
        if ($data['email'] && !valid_email($data['email'])) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!$errors) {
            try {
                $data['photo'] = Upload::fromField('photo', 'image', 'profiles', 'testimonies');
            } catch (UploadException $e) {
                $errors['photo'] = $e->getMessage();
            }
        }
        if ($errors) {
            self::fail($errors);
        }
        DB::insert('testimonies', $data);
        log_activity('submit', 'testimonies', 'New testimony submitted by ' . $data['name']);
        respond(true, 'Praise God! Your testimony has been received and will be reviewed before publishing.', 'testimonies.php', ['reset' => true]);
    }

    public static function contact(): never
    {
        self::guard('contact');
        $errors = [];
        $data = [
            'name'    => self::text('name', 120, true, 'Your name', $errors),
            'email'   => self::text('email', 190, true, 'Email', $errors),
            'phone'   => self::text('phone', 40, false, 'Phone', $errors),
            'subject' => self::text('subject', 200, false, 'Subject', $errors),
            'message' => self::text('message', 5000, true, 'Message', $errors),
            'ip_address' => client_ip(),
        ];
        if ($data['email'] && !valid_email($data['email'])) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($errors) {
            self::fail($errors);
        }
        DB::insert('contact_messages', $data);
        respond(true, 'Thank you for reaching out! We will get back to you soon.', 'contact.php', ['reset' => true]);
    }

    public static function giving(): never
    {
        self::guard('giving', 10);
        $errors = [];
        $currencies = array_map('trim', explode(',', setting('giving_currencies', 'USD')));
        $amount = str_replace(',', '', post('amount'));
        $categoryId = (int) post('category_id');
        $methodId = (int) post('method_id');
        $anonymous = !empty($_POST['is_anonymous']);

        if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 1000000) {
            $errors['amount'] = 'Enter a valid amount.';
        }
        $currency = in_array(post('currency'), $currencies, true) ? post('currency') : null;
        if (!$currency) {
            $errors['currency'] = 'Choose a currency.';
        }
        if (!DB::value('SELECT COUNT(*) FROM giving_categories WHERE id = ? AND is_active = 1', [$categoryId])) {
            $errors['category_id'] = 'Choose what you are giving towards.';
        }
        $method = DB::one('SELECT * FROM giving_methods WHERE id = ? AND is_active = 1', [$methodId]);
        if (!$method) {
            $errors['method_id'] = 'Choose a payment method.';
        }
        $name = self::text('donor_name', 120, !$anonymous, 'Your name', $errors);
        $email = self::text('email', 190, false, 'Email', $errors);
        $phone = self::text('phone', 40, false, 'Phone', $errors);
        $paymentRef = self::text('payment_reference', 100, false, 'Transaction ID', $errors);
        if ($email && !valid_email($email)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($errors) {
            self::fail($errors);
        }

        $tx = [
            'reference'         => Giving::newReference(),
            'category_id'       => $categoryId,
            'method_id'         => $methodId,
            'amount'            => round((float) $amount, 2),
            'currency'          => $currency,
            'donor_name'        => $name,
            'email'             => $email,
            'phone'             => $phone,
            'is_anonymous'      => $anonymous ? 1 : 0,
            'payment_reference' => $paymentRef,
            'status'            => 'pending',
            'source'            => 'online',
        ];
        $tx['id'] = DB::insert('giving_transactions', $tx);
        log_activity('submit', 'giving', 'Online gift submitted: ' . $tx['reference']);

        // Hand off to an online gateway when one is configured for this method.
        $redirect = PaymentGateway::for($method)->initiate($tx, $method);
        if ($redirect) {
            respond(true, 'Redirecting you to complete your gift…', null, ['redirect' => $redirect]);
        }
        respond(true, 'Thank you for your generosity! Your reference is ' . $tx['reference'] . '. We will confirm your gift shortly.', 'giving.php', ['reset' => true, 'reference' => $tx['reference']]);
    }
}
