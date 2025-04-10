WORDPRESS GOGEOTHERMAL STEPS


first time activation

01 activate and then manually go to admin
02 run setup wizard
    01 authenticate and save access token
    02 option to synch products
        01 notice to drop existing products before importing new products and that they can be recovered from deleted
        02 press synch which then calls api and pulls all the products and inserts them with default prices
        03 if additional price groups add this as meta data to the product so we can read it later
    04 option to synch users
        01 connect to api and pull users
        02 insert into db and generate password for accounts with email only and send account info
        03 add meta data to user profile if they have credit or not
        04 log and show any users that failed to create propbably due to no email


general running

01 on product page, if user has a discount group then show discount price instead
02 checkout
    01 if user has discount then show prices as normal and apply the discount to the total cart against the specific products
    02 if user is credit then checkout is all the way through with credit payment method
    03 if no credit then normal stripe checkout
03 account
    01 in account with order list they can access another page with status updates
    02 this contains the order status
    03 this also contains the delivery updates
    04 they can turn on and off email notifications in this section
    05 for completed orders, an "Order Progress" button appears showing real-time shipping and delivery updates