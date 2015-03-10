Laravel 5 Route Annotation with Generator
==========================================

Library ini memungkinkan Laravel untuk mendaftarkan Route melalui anotasi pada Method di Controller
dan men-generate route anotasi tersebut menjadi standar Routing Laravel. 

## Instalasi

* pada root project laravel, jalankan perintah `composer require 'emsifa/route-annotation'`
* pada `config/app.php`, di bagian `providers` tambahkan `Emsifa\RouteAnnotation\RouteAnnotationServiceProvider`
* jalankan `php artisan list`, jika terdapat command `route:standarize` tandanya instalasi sukses

## Route::annotate($controller)

`Route::annotate` digunakan untuk scan `DocComment`(anotasi) pada method di controller yang didaftarkan.

#### Contoh Penggunaan

file `app/Http/Controllers/YourController.php`:

```php
<?php namespace App\Http\Controllers;

class YourController extends Controller {

	/**
	 * @route 	GET /foo
	 */
	public function foo()
	{
		echo "Foo";
	}

	/**
	 * @route 	GET /foobar
	 * @name 	foobar
	 */
	public function foobar($value='')
	{
		echo "Foobar";
	}

	/**
	 * @route 	GET /hello/{name}/{age}
	 * @name 	hello
	 * @param 	string $name 	/[a-zA-Z_-]+/
	 * @param 	string $age 	/\d+/
	 */
	public function hello($name, $age)
	{
		echo "Hello {$name} ({$age}).";
	}

	/**
	 * @route 		POST /edit/{post_id}
	 * @name 		post-edit
	 * @middleware 	auth
	 * @param 		string $post_id /\d+/
	 */
	public function editPost($post_id)
	{
		// do something
	}

}
```

file `app/Http/routes.php`:

```php
<?php

Route::annotate('YourController');

```

Contoh diatas jika menggunakan standar Routing Laravel akan seperti:

```php
<?php

Route::get('/foo', 'YourController@foo');

Route::get('/foobar', [
	'uses' => 'YourController@foobar',
	'as' => 'foobar'
]);

Route::get('/hello/{name}/{age}', [
	'uses' => 'YourController@hello',
	'as' => 'hello'
])
->where('name', '[a-zA-Z_-]+')
->where('age', '\d+');

Route::get('/edit/{post_id}', [
	'uses' => 'YourController@editPost',
	'as' => 'post-edit',
	'middleware' => 'auth'
])->where('post_id', '\d+');

```

## Command `route:standarize`

Cara kerja library ini adalah dengan men-scan `DocComment` pada setiap Controller yang di `annotate`.
Karena itu pada saat production direkomendasikan menggunakan standar Routing Laravel agar lebih ramah performance dan mengurangi resource pada server. 
Untuk itu library ini menyediakan sebuah artisan command untuk men-generate standar routing Laravel 5 ke dalam file routes.php. Untuk melakukan hal tersebut, kamu cukup jalankan `php artisan route:standarize` pada terminal/cmd.

