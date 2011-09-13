# Resource Infobox for WordPress

# Using

A shortcode, which can be inserted into a post or a page, is provided:

    [resource-infobox url="https://github.com/benatkin/wordpress-resource-infobox/"]

When the page is rendered, this plugin will attempt to find a service
definition for the resource. If one is found, it will fetch the data
and display an Infobox according to the resource definition.

# Configuring

## Service Definitions

Not yet written. This will be a way to specify things that are specific
to a service, like GitHub or Wikipedia.

## Resource Definitions

Resource Definitions contain the rules for retrieving a kind of
resource and rendering an infobox for it. The format is as follows:

*   **url**: The URL of the resource's web page. This will
*   **api_url**: The URL of the JSON data for the resource
*   **fields**: An array of field objects, which contain:
    *   **label**: A descriptive label for the field
    *   **param**: The name of a parameter. Optional. **path** may be
        used instead.
    *   **path**: The jsonpointer path of a resource. Example: to get
        `city` from `{"location": {"city": "Boulder"}}`, the path
        will be "location/city"
    *   **type**: Optional. Currently *date* is supported.
    *   **format**: Optional. Currently can only be used for fields 
        with the type *date*. Example: *Y-m-d*.

