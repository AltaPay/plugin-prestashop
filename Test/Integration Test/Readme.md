# How to run cypress test successfully in your environment 

In order to run the test cases successfully, you need to follow the mentioned guidlines.

## Prerequisites: 

1) PrestaShop 1.6.X should be installed on publically accessible URL
2) Cypress should be installed

## Steps 

1) First we need to install required packages using `npm i`
Next, to set the your URL and Credentials please proceed by following mentioned steps:

2) Search for the file "cypress/fixtures/config.json" and Change the parameters below according to your environment and save the file.

{
  "url": "http://sampledomain.com",
  "usrname_cust": "demo@mail.com",
  "pass_cust": "yourpassword",
  "url_admin":"http://sampledomain.com/admin",
  "usrname_admin":"admin_user",
  "pass_admin":"admin_password"
}

3) Run `npm run cypress_run` to run all the tests
