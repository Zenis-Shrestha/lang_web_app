Version: V1.0.0

# NSD Api

The External API Service allows users to insert and publish indicator data into the National Sanitation Dashboard (NSD).

### NsdDashboardController

app\\Http\\Controllers\\Fsm\\NsdDashboardController.php

The controllers main function is to validate and verify the data to push to the NSD.

The basic classes of the controller are:

| **Function**    | \_construct()                                                                      |
| --------------- | ------------------------------------------------------------------------------------ |
| **Description** | Initializes authentication credentials and API endpoints from environment variables. |
| **Parameters**  |                                                                                      |
| **Return**      | null                                                                                 |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php                                |

| **Function**    | pushtoNsd()                                                                    |
| --------------- | ------------------------------------------------------------------------------ |
| **Description** | Pushes CWIS data for the given year to NSD after validation and authentication |
| **Parameters**  | \$year                                                                         |
| **Return**      | Success or error message                                                       |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php                          |

| **Function**    | getBearerToken()                                      |
| --------------- | ----------------------------------------------------- |
| **Description** | Handles process of getting and storing bearer token   |
| **Parameters**  |                                                       |
| **Return**      | null                                                  |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php |

| **Function**    | getCwisData()                                         |
| --------------- | ----------------------------------------------------- |
| **Description** | Handles process of getting CWIS Data from db          |
| **Parameters**  | \$year                                                |
| **Return**      | null                                                  |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php |

| **Function**    | postToNSD()                                           |
| --------------- | ----------------------------------------------------- |
| **Description** | Sends CWIS data for the specified year to NSD         |
| **Parameters**  | \$year, \$year                                        |
| **Return**      | null                                                  |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php |

| **Function**    | checkNsdStatus()                                      |
| --------------- | ----------------------------------------------------- |
| **Description** | Checks the status of Indicators in NSD                |
| **Parameters**  |                                                       |
| **Return**      | Returns data of Indicators                            |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdDashboardController.php |

## NSD Setting

The Admin can set required credentials here to access the API.

### Tables

Nsd Setting is under FSM module and uses the following table:

-   nsd_setting

The corresponding tables have their respective models that are named in Pascal Case in singular form. Nsd Setting model is located at app\\Models\\Fsm\\.

### Views

All views used by this module is stored in resources\\views\\Fsm\\nsd-setting

-   nsd-setting.create: opens form and calls partial-form for form contents

-   nsd-setting.partial-form: creates form content

-   nsd-setting.edit: opens form and calls partial-form for form contents

### NsdSettingController

app\\Http\\Controllers\\Fsm\\NsdSettingController.php

The controller main function is to set the credentials for the Admin.

The basic classes of the controller are:

| **Function**    | index()                                                                               |
| --------------- | ------------------------------------------------------------------------------------- |
| **Description** | Guide the user to edit or update the credentials in accordance with the requirements. |
| **Parameters**  |                                                                                       |
| **Return**      | fsm/nsd-setting.create or fsm/nsd-setting.edit                                        |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdSettingController.php                                 |

| **Function**    | create()                                              |
| --------------- | ----------------------------------------------------- |
| **Description** | Returns the form to create new credentials            |
| **Parameters**  |                                                       |
| **Return**      | fsm/nsd-setting.edit compact('page_title')            |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdSettingController.php |

| **Function**    | edit()                                                |
| --------------- | ----------------------------------------------------- |
| **Description** | Returns the edit form page displaying credentials     |
| **Parameters**  | \$id                                                  |
| **Return**      | fsm/nsd-setting.edit compact('page_title')            |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdSettingController.php |

| **Function**    | store()                                               |
| --------------- | ----------------------------------------------------- |
| **Description** | Handles process of storing the credentials            |
| **Parameters**  | \$request                                             |
| **Return**      | Success or error message.                             |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdSettingController.php |

| **Function**    | update()                                              |
| --------------- | ----------------------------------------------------- |
| **Description** | Handles process of updating the credentials           |
| **Parameters**  | \$request, \$id                                       |
| **Return**      | Success or error message.                             |
| **Source**      | app\\Http\\Controllers\\Fsm\\NsdSettingController.php |
