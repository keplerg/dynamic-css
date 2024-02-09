# Dynamic-css
Lightweight Dynamic CSS similar to SASS that automatically detects CSS changes and caches compiled CSS. Works with Apache server and PHP.

# What can it do for me?
This library will allow you to:

- Define variables in your CSS file as well as access PHP environment variables.
- Use conditional logic (if/else) in your CSS file.
- Speed up your web site by combining CSS files and compressing them on the server.
- Automatically recompiles CSS files without the need to install a daemon process.
- Apply an alpha filter for PNG support, or substitute a GIF, by detecting the browser.
- Save money because it is FREE.

# Speed up your web site
This library compresses your CSS files automatically and sends the file to your browser using Gzip (if your browser supports it).

The CSS file for this web site decreased from 11237 bytes to 2006 bytes. That's less than 1/5 the original size!

This library also adds a 1 year expiration header to the CSS file. This prevents the same file being requested over and over again.

Do you have multiple CSS files that you combine using @import? Use the @include command instead and combine your CSS files on the server.

# Automatically recompile CSS on changes
Changes made to your CSS or any dependent  CSS files (@include'd) will be detected and force a recompile. There is no external compile step as with LESS and SASS.

Subsequent requests for the same CSS will be served from the cache. Caching is automatic and the cache directory will be created if it doesn't already exist.

# Variables and program logic in your CSS file(s).
CSS is a great way to keep your presentation out of your content (HTML). Wouldn't it be nice to be able to be able to make global color or back ground changes for example? With this library you can!

Just set variables at the top of your CSS file like:

```
set $color = #33CCFF;
set $background-color = #000000;
```
Now everywhere you use these colors, just use the variables instead:

```
p {
    color: $color;
    background-color: $background-color;
}
```
To change the colors throughout your entire CSS file, you only need to change the variables at the top now.

What if your web site has different themes? Use conditional logic to either switch between different blocks of CSS:

```
.body {
     if ( $_COOKIE['theme'] == 'bluesky' )
         color: #3366FF;
     else
         color: #000000;
     endif
}
```
or include a theme based CSS file conditionally:

```
.body {
    if ( $_COOKIE['theme'] == 'bluesky' )
         @include 'bluesky-theme.css';
     else
         @include 'default-theme.css';
     endif
}
```

# Fix PNG handling automatically
The PNG image format and it's support of alpha transparency and lossless compression make for stunning graphics. The problem is older browsers don't support it and IE6 requires a filter added to your CSS file to support it. This is not much of an issue these days as newer browsers support PNG.

This library detects PNG images in your CSS file and, depending on the requesting browser, can add the filter or switch to a GIF or PNG8 image automatically. It will create the GIF or PNG8 image if it doesn't exist.

# The best part is it's free!
The code is distributed under the LGPL license. Just leave the copyright notice at the top and you can use it as much as you want with no restrictions.

The documentation is included in the download. Please feel free to send me comments or suggestions below.
