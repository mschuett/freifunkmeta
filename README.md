# Freifunk Metadata Wordpress Plugin

A small Wordpress plugin to render Freifunk metadata according to the [api.freifunk.net](https://github.com/freifunk/api.freifunk.net) specification ([german description](http://freifunk.net/blog/2013/12/die-freifunk-api/)).

It reads (and caches) JSON input from a configured URL and provides shortcodes to output the data.

Currently implemented are `[ff_services]`, `[ff_contact]`, and `[ff_state]`.

An `[ff_location]` is also usable, but needs more work.

## Example

Text:

    Location:

    [ff_location]

    Services:

    [ff_services]

    Contact:

    [ff_contact]

    Contact Jena:

    [ff_contact jena]

Output:

![_location output example](http://mschuette.name/wp/wp-upload/freifunk_meta_location_sample.png)

![shortcode output example](http://mschuette.name/wp/wp-upload/freifunk_meta_example.png)

