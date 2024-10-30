# Swisscom MAB + ETSI RDSC add-on for the SetaPDF-Signer component

This add-on offers an individual signature module and helper functionalities for the
[SetaPDF-Signer Component](https://www.setasign.com/signer) that allows you to use the 
[Swisscom MAB (multi authentication broker) and ETSI RDSC Service](https://trustservices.swisscom.com/signing-service/)
 to digital **sign PDF documents in pure PHP**. 

Currently, the implementation mainly adds some helpers to make the communication
with the swisscom API easier. Only PAR requests are demonstrated and implemented yet.


## Requirements
To use this add-on you need credentials for the Swisscom MAB ETSI RDSC webservice.

This add-on is developed and tested on PHP >= 8.0. Requirements of the [SetaPDF-Signer](https://www.setasign.com/signer)
component can be found [here](https://manuals.setasign.com/setapdf-signer-manual/getting-started/#index-1).

We're using [PSR-17 (HTTP Factories)](https://www.php-fig.org/psr/psr-17/) and [PSR-18 (HTTP Client)](https://www.php-fig.org/psr/psr-18/)
for the requests. So you'll need an implementation of these. We recommend using Guzzle. Note: Your implementation must
support client-side certificates.

```
    "require" : {
        "guzzlehttp/guzzle": "^7.0",
        "http-interop/http-factory-guzzle": "^1.0"
    }
```

## Installation
Add following to your composer.json:

```json
{
    "require": {
        "setasign/setapdf-signer-addon-swisscom-mab_etsi-rdsc": "dev-main"
    },

    "repositories": [
        {
            "type": "composer",
            "url": "https://www.setasign.com/downloads/"
        }
    ]
}
```

and execute `composer update`. You need to define the `repository` to resolve the dependency to the
[SetaPDF-Signer](https://www.setasign.com/signer) component
(see [here](https://getcomposer.org/doc/faqs/why-can%27t-composer-load-repositories-recursively.md) for more details).

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
