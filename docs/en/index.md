Quickstart
==========

This extension is fork of the original [Facebook SDK](https://github.com/facebook/facebook-php-sdk).
And the nicest thing I can say about the original SDK is that it's working. And don't get me wrong,
having a working PHP SDK is great! But it doesn't even begin to meet the Nette standard.

So it has been completely rewritten here to use full power of Nette Framework.



Installation
-----------

The best way to install Kdyby/Facebook is using  [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/facebook:@dev
```

With dev Nette, you can enable the extension using your neon config.

```yml
extensions:
	facebook: Kdyby\Facebook\DI\FacebookExtension
```

If you're using stable Nette, you have to register it in `app/bootstrap.php`

```php
Kdyby\Facebook\DI\FacebookExtension::register($configurator);

return $configurator->createContainer();
```



Minimal configuration
---------------------

This extension creates new configuration section `facebook`, the absolute minimal configuration is app ID and secret.
You also might wanna provide default required permissions.

```yml
facebook:
	appId: "1234567890"
	appSecret: "e807f1fcf82d132f9bb018ca6738a19f"
	permissions: [email]
```

And that's all.



Calling Facebook API
--------------------

On the original PHP SDK, there is a method `api()` on the facebook class.
The usage is 100% identical with the original SDK, so you can just follow their documentation on how to use it.



Authentication
--------------

Hey, why there is no authenticator? Well, it's because the Facebook login sustains of several HTTP redirects,
and as you might have noticed, those don't fit well into PHP objects.

Then how are we going to login to Facebook you may ask? Easily! There is a component for that!

```php
use Kdyby\Facebook\Facebook;
use Kdyby\Facebook\Dialog\LoginDialog;
use Kdyby\Facebook\FacebookApiException;
use Nette\Diagnostics\Debugger;

class LoginPresenter extends BasePresenter
{

	/** @var Facebook */
	private $facebook;

	/** @var UsersModel */
	private $usersModel;

	/**
	 * You can use whatever way to inject the instance from DI Container,
	 * but let's just use constructor injection for simplicity.
	 *
	 * Class UsersModel is here only to show you how the process should work,
	 * you have to implement it yourself.
	 */
	public function __construct(Facebook $facebook, UsersModel $usersModel)
	{
		parent::__construct();
		$this->facebook = $facebook;
		$this->usersModel = $usersModel;
	}


	/** @return LoginDialog */
	protected function createComponentFbLogin()
	{
		$dialog = $this->facebook->createDialog('login');
		/** @var LoginDialog $dialog */

		$dialog->onResponse[] = function (LoginDialog $dialog) {
			$fb = $dialog->getFacebook();

			if ( ! $fb->getUser()) {
				$this->flashMessage("Sorry bro, facebook authentication failed.");
				return;
			}

			/**
			 * If we get here, it means that the user was recognized
			 * and we can call the Facebook API
			 */

			try {
				$me = $fb->api('/me');

				if (!$existing = $this->usersModel->findByFacebookId($fb->getUser())) {
					/**
					 * Variable $me contains all the public information about the user
					 * including facebook id, name and email, if he allowed you to see it.
					 */
					$existing = $this->usersModel->registerFromFacebook($fb->getUser(), $me);
				}

				/**
				 * You should save the access token to database for later usage.
				 *
				 * You will need it when you'll want to call Facebook API,
				 * when the user is not logged in to your website,
				 * with the access token in his session.
				 */
				$this->userModel->updateFacebookAccessToken($fb->getUser(), $fb->getAccessToken());

				/**
				 * Nette\Security\User accepts not only textual credentials,
				 * but even an identity instance!
				 */
				$this->user->login(new \Nette\Security\Identity($existing->id, $existing->roles, $existing));

				/**
				 * You can celebrate now! The user is authenticated :)
				 */

			} catch (FacebookApiException $e) {
				/**
				 * You might wanna know what happened, so let's log the exception.
				 *
				 * Rendering entire bluescreen is kind of slow task,
				 * so might wanna log only $e->getMessage(), it's up to you
				 */
				Debugger::log($e, 'facebook');
				$this->flashMessage("Sorry bro, facebook authentication failed hard.");
			}

			$this->redirect('this');
		};

		return $dialog;
	}

}
```

And in template, you might wanna render a link to open the login dialog.

```smarty
{* By the way, this is how you do a link to signal of subcomponent. *}
<a n:href="fbLogin-open!">Login using facebook</a>
```

Now when the user clicks on this link, he will get redirected to facebook app authentication page,
where he can allow your page or decline it. When he confirms the privileges your application requires,
he will be redirected back to your website. Because we've used Nette components,
he will be redirected to a signal on the `LoginDialog`, that will invoke the event
and your `onResponse` callback will be invoked. And from there, it's child play.



Best practices
--------------

Please keep in mind that the user can revoke the access to his account literary anytime he want's to.

Therefore you must wrap every facebook api call with `try catch`

```php
try {
	// ...
} catch (\Kdyby\Facebook\FacebookApiException $e) {
	// ...
}
```

and if it fails, try requesting the list of your permissions.
This will tell you if the user revoked your application entirely, or he only removed some of your privileges.

And if he revokes your application, drop the access token, it will never work again, you may only acquire a new one.
