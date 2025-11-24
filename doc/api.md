# Symposium API v1

### Resources

Type                              | Resource                                                                    | Description
----------------------------------|-----------------------------------------------------------------------------|------------
[Conferences](#conferences)       | [GET /api/conferences](#conferences)                                        | Conferences
[Conference](#conference)         | [GET /api/conference/:id](#conference)                                      | Conference
[User Talks](#talks)              | [GET /api/users/:id/talks](#talks)                                          | Talks for authenticated user
[Talk](#talk)                     | [GET /api/talks/:id](#talk)                                                 | Talk information
[User Bios](#bios)                | [GET /api/users/:id/bios](#bios)                                            | Bios for authenticated user
[Bio](#bio)                       | [GET /api/bios/:id](#bio)                                                   | Bio information
[Me](#me)                         | [GET /api/me](#me)                                                          | Information about authenticated user

### General API Guidelines

**Encoding**

All data sent to and from the API should be encoded as UTF-8.

**Dates and DateTimes**

Unless specified otherwise, all Dates and DateTime strings sent to and from the
API will be sent in (? @todo);

### Authentication / Signing
See [OAuth](#oauth).

### Pagination
@todo

## ERROR
@todo

## CONFERENCES

A listing of all conferences.

Default sort is "CFP closing next"; default filter is "unclosed CFP".

##### REQUEST

```
GET /api/conferences
```

Optionally takes `sort` and `filter` parameters.

Sort takes `alpha`, `date`, and `closing_next` (default).
Sort allows prefacing with `-` per JSON-API (e.g. `?sort=-date`)

Filter takes `all`, `future`, `open_cfp`, and `unclosed_cfp` (default).

##### RESPONSE

```json
{
  "data": [
    {
      "id": "07098561-6df3-4368-b88a-aad1a7531a50",
      "title": "ActiveRecordCon 2015",
      "description": "Culpa labore in sed molestias quos.",
      "url": "http://example.com",
      "author_id": 3,
      "starts_at": "2015-07-20 22:40:21",
      "ends_at": "2015-07-22 22:40:21",
      "cfp_starts_at": "2015-06-26 05:54:10",
      "cfp_ends_at": "2015-07-22 05:54:10",
      "created_at": "2015-06-06 09:16:04",
      "updated_at": "2015-06-06 09:16:04",
      "cfp_url": "http://example.com/cfp",
      "is_approved": true,
      "is_shared": false,
      "location": null,
      "calling_all_papers_id": "aabbccddeeff11223344556677889900abcdef12"
    },
    {
      "id": "0747de8f-ad9e-4cc5-b33d-922be66bef12",
      "title": "UltraMegaCon 2016",
      "description": "Eum aspernatur voluptate quia ut error omnis sit.",
      "url": "http://example.com",
      "author_id": 11,
      "starts_at": "2016-09-15 17:50:49",
      "ends_at": "2016-09-17 17:50:49",
      "cfp_starts_at": "2015-07-22 04:14:10",
      "cfp_ends_at": "2015-08-14 04:14:10",
      "created_at": "2015-06-06 09:16:04",
      "updated_at": "2015-06-06 09:16:04",
      "cfp_url": null,
      "is_approved": true,
      "is_shared": false,
      "location": null,
      "calling_all_papers_id": "abcdef1234567890abcdef1234567890abcdef12"
    }
  ]
}
```

## CONFERENCE

Information about a conference.

##### REQUEST

```
GET /api/conferences/:id
```

##### RESPONSE

```json
{
  "data": {
    "id": "07098561-6df3-4368-b88a-aad1a7531a50",
    "type": "conferences",
    "attributes": {
      "title": "ActiveRecordCon 2015",
      "description": "Culpa labore in sed molestias quos.",
      "url": "http://example.com",
      "cfp_url": "http://example.com/cfp",
      "starts_at": "2015-07-20 22:40:21",
      "ends_at": "2015-07-22 22:40:21",
      "cfp_starts_at": "2015-06-26 05:54:10",
      "cfp_ends_at": "2015-07-22 05:54:10"
    }
  }
}
```

## TALKS

A listing of all talks for the authenticated user.

##### REQUEST

```
GET /api/users/:id/talks
```

##### RESPONSE

```json
{
  "data": [
    {
      "type": "talks",
      "id": "d426e5f8-cf65-42bc-bb00-9da9b5606a2d",
      "attributes": {
        "title": "My great talk",
        "description": "Description of the talk",
        "created_at": "2013-11-29 15:54:41",
        "updated_at": "2015-05-31 10:48:38",
        "type": "seminar",
        "length": 45,
        "public": true,
        "level": "intermediate",
        "organizer_notes": "Organizer notes",
        "slides": "http://www.speakerdeck.com/09140194h091241"
      }
    },
    {
      "type": "talks",
      "id": "d8db8693-7948-4476-b098-d22e003d8c54",
      "attributes": {
        "title": "My terrible talk",
        "description": "Description of the talk",
        "created_at": "2013-11-29 15:54:41",
        "updated_at": "2015-05-31 10:48:38",
        "type": "seminar",
        "length": 45,
        "public": false,
        "level": "intermediate",
        "organizer_notes": "Organizer notes",
        "slides": "http://www.speakerdeck.com/j09j341241"
      }
    }
  ]
}
```

## TALK

Information about a talk.

##### REQUEST

```
GET /api/talks/:id
```

##### RESPONSE

```json
{
  "data": {
    "type": "talks",
    "id": "d426e5f8-cf65-42bc-bb00-9da9b5606a2d",
    "attributes": {
      "title": "My great talk",
      "description": "Description of the talk",
      "created_at": "2013-11-29 15:54:41",
      "updated_at": "2015-05-31 10:48:38",
      "type": "seminar",
      "length": 45,
      "public": true,
      "level": "intermediate",
      "organizer_notes": "Organizer notes",
      "slides": "http://www.speakerdeck.com/oithoi1241421"
    }
  }
}
```

## BIOS

A listing of all bios for the authenticated user.

#### REQUEST

```
GET /api/users/:id/bios
```

##### RESPONSE

```json
{
  "data": [
    {
      "type": "bios",
      "id": "0603a7d7-63e8-4ae1-9f38-760564d2049e",
      "attributes": {
        "nickname": "Long Bio",
        "body": "I am short and I love being short and this is very long.",
        "public": true,
        "created_at": "2015-05-31 10:48:38",
        "updated_at": "2015-05-31 10:48:38"
      }
    },
    {
      "type": "bios",
      "id": "2cb86717-cf08-499d-b6aa-f2463200f9c2",
      "attributes": {
        "nickname": "Short Bio",
        "body": "I am short.",
        "public": false,
        "created_at": "2015-05-31 10:48:38",
        "updated_at": "2015-05-31 10:48:38"
      }
    }
  ]
}
```

## BIO

Information about a bio.

##### REQUEST

```
GET /api/bios/:id
```

##### RESPONSE

```json
{
  "data": {
    "type": "bios",
    "id": "0603a7d7-63e8-4ae1-9f38-760564d2049e",
    "attributes": {
      "nickname": "Long Bio",
      "body": "I am short and I love being short and this is very long.",
      "public": true,
      "created_at": "2015-05-31 10:48:38",
      "updated_at": "2015-05-31 10:48:38"
    }
  }
}
```

## ME
Information about the currently authenticated user.

##### REQUEST

```
GET /api/me
```

##### RESPONSE

```json
{
  "data": {
    "type": "users",
    "id": 1,
    "attributes": {
      "email": "matt@symposiumapp.com",
      "name": "Matt Stauffer",
      "created_at": "2015-05-31 10:48:38",
      "updated_at": "2015-05-31 10:48:38"
    }
  }
}
```

## OAuth
Symposium uses the OAuth2 Authorization Code grant.

Routes:
Authorization form: `https://symposiumapp.com/oauth/authorize?client_id=:id&redirect_uri=:redirect_uri&response_type=code`

The authorization form presents users with the ability to approve or deny the connection. If they approve, it'll forward them to:
`:redirect_url/?code=code-for-getting-token-below`

Grab that code, and pass it to `https://symposiumapp.com/oauth/token` to get your access token for that user.

E.g.

```bash
curl -u client_id:client_secret https://symposiumapp.com/oauth/token -d 'grant_type=authorization_code&code=:code-from-above&redirect_uri=:redirect_url'
```

Finally, you can authorize your requests using the provided token:
![Authorization example using PAW](https://s3.amazonaws.com/f.cl.ly/items/2g1a3x1x0m2V2O3v2447/Screen%20Shot%202015-06-20%20at%202.25.57%20PM.png)
