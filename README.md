JSON REST API Subscriptions [![Build Status](https://travis-ci.org/tlovett1/json-rest-api-subscriptions.svg?branch=master)](https://travis-ci.org/tlovett1/json-rest-api-subscriptions)
===============

Enable subscriptions to posts, pages, and custom post types in WordPress. Users can securely subscribe via simple API routes to new content, content updates, and content deletes for post types or single pieces of content.

## Purpose

If you are publishing content and have users/websites digesting your content, you may have been faced with the problem: how do I get updates to users immediately? In the past users/websites have subscribed to feeds or used techniques like "polling" to constantly ping your site for new content. Both these techniques are cumbersome and old fashioned. JSON REST API Subscriptions creates new API endpoints to allow people to subscribe to new content, content updates, and content deletes across general post types as well as single pieces of content.

## Requirements

* WordPress 4.4+
* PHP 5.2.4+
* [JSON REST API 2.0beta12+](https://wordpress.org/plugins/rest-api/)

## Installation

Install the plugin in WordPress, you can download a
[zip via Github](https://github.com/tlovett1/json-rest-api/archive/master.zip) and upload it using the WP
plugin uploader.

Once the plugin is activated, there is no configuration necessary. Endpoints will become available immediately.


## Usage

### Endpoints

Users and websites can subscribe to your content by sending HTTP requests to the following endpoints:

* `/wp-json/<post-type>/subscriptions`

  Subscriptions to this endpoint well result in receiving updates for all posts with the given post type.
  
  *Supports:*
  * GET - Get all subscriptions
  * POST - Create a new subscription
  * PUT  - Update a particular subscription
  * DELETE - Delete a particular subscription

* `/wp-json/<post-type>/<post-id>/subscriptions`

  Subscriptions to this endpoint well result in receiving updates for the post with the given post ID.
  
  *Supports:*
  * GET - Get all subscriptions
  * POST - Create a new subscription
  * PUT  - Update a particular subscription
  * DELETE - Delete a particular subscription

#### Creating a New Subscription

Send an HTTP request to a valid subscription endpoint like so:

```
POST http://example.com/wp-json/posts/subscriptions
Content-Type: application/json

{
  "events": ["update", "delete", "create"],
  "target": "http://hook-destination.com"
}
```

`events` is the type of changes you will be notified of. Valid `events` are `update`, `create`, and `delete`. `target` is the URL that will be sent notifications.

After creating a subscription, the return response will content a `X-WP-Subscription-Signature` header. This header is your *password* for deleting and updating this subscription in the future.

#### Updating a Subscription

Send an HTTP request to a valid subscription endpoint like so:

```
PUT http://example.com/wp-json/posts/subscriptions
X-WP-Subscription-Signature: oSDFfgo3698hwodfn2lt04sdg
Content-Type: application/json

{
  "events": ["update"],
  "target": "http://hook-destination.com"
}
```

This request would update the subscription to all posts for `http://hook-destination.com` to only send notifications for `update` events. Note that `X-WP-Subscription-Signature` is required for authentication.

#### Deleting a Subscription

Send an HTTP request to a valid subscription endpoint like so:

```
DELETE http://example.com/wp-json/posts/subscriptions
X-WP-Subscription-Signature: oSDFfgo3698hwodfn2lt04sdg
Content-Type: application/json

{
  "target": "http://hook-destination.com"
}
```

This request would delete the subscription to all posts for `http://hook-destination.com`. Note that `X-WP-Subscription-Signature` is required for authentication.

### Notification Request

When a subscription is triggered, a notification will be sent that looks like this:

```
POST https://mynotificationtarget.com
Content-Type: application/json
X-WP-Notification: https://subscriptionsite.com

{  
   "action": "update",
   "item":{  
      "id": 3216,
      "date": "2016-02-13T05:13:05",
      "date_gmt": "2016-02-13T05:13:05",
      "guid":{  
         "rendered": "https:\/\/subscriptionsite.com\/?p=3216",
         "raw": "https:\/\/subscriptionsite.com\/?p=3216"
      },
      "title": {  
         "raw": "Test Post",
         "rendered": "Test Post"
      },
      "content": {  
         "raw": "This is my test content",
         "rendered": "This is my test content"
      },
      "excerpt": {  
         "raw": "This is my test excerpt",
         "rendered": "This is my test excerpt"
      },
      "modified": "2016-02-13T05:19:35",
      "modified_gmt": "2016-02-13T05:19:35",
      "slug": "test-post-2",
      "status": "publish",
      "type": "post",
      "link": "https:\/\/subscriptionsite.com\/2016\/02\/13\/test-post-2\/",
      "author": {  
         "user_login": "admin",
         "user_nicename": "admin",
         "user_url": "https:\/\/taylorlovett.com",
         "display_name": "admin"
      }
   }
}
```

*The website receiving the notification request MUST respond with an HTTP 200 status code and an `X-WP-Subscription-Signature` header containing the signature provided when first creating the subscription. Failure to do either of those things will result in the subscription being delete.*

## License

JSON REST API Subscriptions is free software; you can redistribute it and/or modify it under the terms of the [GNU General
Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any
later version.
