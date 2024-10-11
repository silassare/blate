# Blate

Another template engine for php.

# print variable [DONE]

```
{ name }

{ '' + name }

{ '{}' }
```

By default all printed value will be escaped to prevent XSS
to print a variable without escaping use:

```
{= unsafe}
```

# set var [DONE]

```
{@set
    myVarA = 2;
    myVarB = auto * 5;
    myVarC = expression;
}
```

# scoped [DONE]

```
{@set foo = 8;
      bar = foo + 9}

foo = {foo}
bar = {bar}

{@scoped}
--SCOPE START--
    {@set foo = foo + 5}
    foo = {foo}
    bar = {bar}
--SCOPE END--
{/scoped}

foo = {foo}
bar = {bar}
```

# if, else and elseif [DONE]

```
{@if expression}
	Wooah!
{/if}

{@if expression_a}
	Hello!
{:elseif expression_b}
	Morning!
{:else}
	Goodbye!
{/if}
```

# each [DONE]

```
{@each value:key:index in list}
	- {value}
{/each}

{@each value:key in list}
	- {value}
{/each}

{@each value in list}
	- {value}
{/each}
```

# inheritance [DONE]

> any template with at least one slot could be inherited

## Slot definition

> a slot name should be unique in the file

```
{@slot name}default content{/slot}
```

## base.blate

```html
<!DOCTYPE html>
<html lang="{lang}">
	<head>
		<title>{@slot title}Blabla WebSite{/slot}</title>
	</head>
	<body>
		{@slot body}Welcome to Blabla WebSite{/slot}
		<ul>
			{@each value:key:index in list}
			<li>{@slot item}{value}{/slot}</li>
			{/each}
		</ul>
	</body>
</html>
```

## contacts.blate

> `context` must be an `Array|Object`

> all `extends` children should be only slot and white space

```html
{@extends 'path/to/base' context} {@slot title}Contacts{/slot} {@slot
body}{:default} Call: +229 00 00 00{/slot} {@slot
item:inject}{inject.index}:{inject.value}{/slot} {/extends}
```

# import [DONE]

> `context` must be an `Array|Object`

```html
{@import 'path/to/file' context}
```

# comment [DONE]

```
{# this is a comment #}

{#
    this is a multiline
    comment
#}
```

# print specials characters [DONE]

```
{ '{}' }
```

# print raw data containing specials characters [DONE]

```
{@raw}
    { } { }
{/raw}
```

# print raw data from a file [DONE]

```
{@import_raw 'file_path'}
```

# injected data context reference [DONE]

> `$$` is the injected data

```html
{@import 'path/to/file' $$}
```

# php code [DONE]

```
{~ echo 'Hello World'; ~}
```

# global helpers [DONE]

define a global helper:

```php
Blate::registerHelper('hello', function (string $name) {
   return 'Hello ' . $name;
});
```

The helper can be used in the template like this:

```html
{hello('world')}
```

or if the current scope has a variable named `hello`:

```html
{$hello('world')}
```

The above will output: `Hello world`.