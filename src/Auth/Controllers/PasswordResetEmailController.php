<?php


    declare(strict_types = 1);


    namespace Snicco\Auth\Controllers;

    use WP_User;
    use Snicco\Auth\Traits\ResolvesUser;
    use Snicco\Auth\Traits\SendsPasswordResetMails;
    use Snicco\Support\WP;
    use Snicco\Http\Controller;
    use Snicco\Http\Psr7\Request;
    use Snicco\Http\Psr7\Response;
    use Snicco\Mail\MailBuilder;
    use Snicco\Traits\ValidatesWordpressNonces;

    class PasswordResetEmailController extends Controller
    {

        use SendsPasswordResetMails;
        use ResolvesUser;

        /**
         * @var MailBuilder
         */
        private $mail;

        public function __construct(MailBuilder $mail)
        {
            $this->mail = $mail;
        }

        public function store(Request $request) : Response
        {

            $user_id = (int) $request->input('user_id', 0);

            $wp_nonce_valid = $request->hasValidAjaxNonce("reset-password-for-$user_id", "nonce");

            if ( ! $wp_nonce_valid) {

                return $this->invalidCsrfCheck();

            }

            if (  ! WP::currentUserCan('edit_user', $user_id) ) {

                return $this->insufficientPermissions();

            }

            $user = $this->getUserById($user_id);

            if ( ! $user instanceof WP_User) {

                return $this->invalidUser();

            }

            $mail_sent = $this->sendResetMail($user);

            if ( ! $mail_sent ) {

                return $this->mailNotSend();

            }

            return $this->success($user);


        }

        private function invalidCsrfCheck() : Response
        {

            return $this->response_factory->json([
                'success' => false, 'data' => __('The link you followed expired'),
            ]);
        }

        private function insufficientPermissions() : Response
        {

            return $this->response_factory->json([
                'success' => false,
                'data' => __('Cannot send password reset, permission denied.'),
            ]);
        }

        private function invalidUser() : Response
        {

            return $this->response_factory->json([
                'success' => false, 'data' => __('The provided user does not exist'),
            ]);
        }

        private function mailNotSend() : Response
        {
            return $this->response_factory->json([
                'success' => false, 'data' => __('The password reset email could not be sent.'),
            ]);
        }

        private function success(WP_User $user) : Response
        {
            return $this->response_factory->json([
                'success' => true,
                'data' => sprintf( __( 'A password reset link was emailed to %s.' ), $user->display_name )
            ]);
        }


    }