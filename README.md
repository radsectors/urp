# **urp** &bull; PHP debug print.


## Basic Usage
```php
use \radsectors\urp as urp;

urp::hello("Hello world.");
urp::number(7);
urp::try_this(4.893);
urp::label(true);
urp::items(['one' => 1, 'two' => 2, 'three' => 'green']);
urp::whatever(new \DateTime());
```

Sample Output:
```
hello: string(12) "Hello world."
number: int(7)
try_this: float(4.893)
label: bool(true)
items: array(3) {
  ["one"] => int(1)
  ["two"] => int(2)
  ["three"] => string(5) "green"
}
whatever: object(DateTime)#3 (3) {
  ["date"] => string(26) "2016-09-23 19:22:16.000000"
  ["timezone_type"] => int(3)
  ["timezone"] => string(15) "America/Chicago"
}
```
