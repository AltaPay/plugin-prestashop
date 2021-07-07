//prestashop_test
require('cypress-xpath')

class Order
{
    clrcookies(){
        cy.clearCookies()
    }
    visit()
    {
        cy.fixture('config').then((url)=>{
        cy.visit(url.shopURL) 
  
            })    
    }
    
    addproduct(){
        cy.get(':nth-child(1) > .product-miniature > .thumbnail-container > .thumbnail > img').click().wait(2000)
        //cy.visit('http://34.254.146.36/en/men/1-1-hummingbird-printed-t-shirt.html#/1-size-s/8-color-white')
        //cy.get('#homefeatured > li.ajax_block_product.col-xs-12.col-sm-4.col-md-3.first-in-line.first-item-of-tablet-line.first-item-of-mobile-line').click()
        //cy.get('.icon-plus').click().click()
        cy.get('.add > .btn').click()
        cy.get('.cart-content-btn > .btn-primary').click()
        //cy.get('.exclusive > span').click()
        //cy.get('.cart-content-btn > .btn-primary').click()
        cy.get('.text-sm-center > .btn').click()
        cy.get(':nth-child(1) > .custom-radio > input').click()
        cy.get('#customer-form > section > :nth-child(2) > .col-md-6 > .form-control').type('Testperson-dk')
        cy.get(':nth-child(3) > .col-md-6 > .form-control').type('Testperson-dk')
        cy.get(':nth-child(4) > .col-md-6 > .form-control').type('demo@example.com')
        cy.get(':nth-child(9) > .col-md-6 > .custom-checkbox > label > input').click()
        cy.get(':nth-child(11) > .col-md-6 > .custom-checkbox > label > input').click()
        cy.get('#customer-form > .form-footer > .continue').click()
        // cy.get('.js-address-form > .form-footer > .continue').click()
        cy.get('.form-fields > :nth-child(7) > .col-md-6 > .form-control').type('Sæffleberggate 56,1 mf')
        cy.get(':nth-child(9) > .col-md-6 > .form-control').type('6800')
        cy.get(':nth-child(10) > .col-md-6 > .form-control').type('Varde')
        cy.get('.js-address-form > .form-footer > .continue').click()
        cy.get('#js-delivery > .continue').click()
        cy.contains('Credit Card').click()
        cy.get('.js-terms').click()
        cy.get('.ps-shown-by-js > .btn').click()
        
        //cy.get('.button-medium > span').click()
        // cy.get('.cart_navigation > .button > span').click()
        //Guest checkout 1.6.X
        // cy.get('#guest_email').type('demo@example.com')
        // cy.get('#firstname').type('Testperson-dk')
        // cy.get('#lastname').type('Testperson-dk')
        // cy.get('#address1').type('Sæffleberggate 56,1 mf')
        // cy.get('#postcode').type('6800')
        // cy.get('#city').type('Varde')
        // cy.get('#id_country').select('Denmark')
        // cy.get('#phone_mobile').type('20123456')
        // cy.get('.cart_navigation > .button > span').click()   
        // cy.get('.cart_navigation > .button > span').click().wait(2000)
        // cy.get('label').click()
        // cy.get('.cart_navigation > .button > span').click().wait(2000)

    }

    cc_payment(){
        //cy.get(':nth-child(3) > .col-xs-12 > .payment_module > .altapay').click()
        cy.get('[id=creditCardNumberInput]').type('4111111111111111')
        cy.get('#emonth').type('01')
        cy.get('#eyear').type('2023')
        cy.get('#cvcInput').type('123')
        cy.get('#cardholderNameInput').type('testname')
        cy.get('#pensioCreditCardPaymentSubmitButton').click().wait(4000)

    }

    klarna_payment(){

        cy.get(':nth-child(2) > .col-xs-12 > .payment_module > .altapay').click().wait(1000)
        cy.get('[id=submitbutton]').click().wait(3000)
        cy.get('[id=klarna-pay-later-fullscreen]').wait(5000).then(function($iFrame){
            const mobileNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-phone-number]')
            cy.wrap(mobileNum).type('(452) 012-3456')
            const personalNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-national-identification-number]')
            cy.wrap(personalNum).type('1012201234')
            const submit = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-continue-button]')
            cy.wrap(submit).click().wait(4000)
            
        })
        
    }

    admin()
    {
            cy.clearCookies()
            cy.fixture('config').then((admin)=>{
            cy.visit(admin.adminURL)
            cy.get('#email').type(admin.adminUsername)
            cy.get('#passwd').type(admin.adminPass).wait(2000)
            cy.get('.ladda-label').click().wait(3000)
            cy.visit(admin.adminURL).wait(3000)
            })

    }

    capture(){

        // This tests work with 1.7.X. Uncomment when the new version is supported.
        // cy.get('#maintab-AdminParentOrders > .title').click()
        
        // cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        // //Capture
        // cy.get('[id=transactionOptions]').then(function($iFrame){
        //     const capture = $iFrame.contents().find('[id=btn-capture]')
        //     cy.wrap(capture).click({force: true})
        //     cy.get('#popup_ok').click()
        //     cy.get('#popup_ok').click()

        
            
            
//         })
//         cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'captured')
        
//         //Refund
//         cy.get('[id=transactionOptions]').then(function($iFrame){
//             const capture = $iFrame.contents().find('[id=btn-refund]')
//             cy.wrap(capture).click({force: true})
//             cy.get(':nth-child(2) > :nth-child(10) > .form-control').type('3').click()
            
//         })
            
//         cy.get('[id=transactionOptions]').then(function($iFrame){
//             const capture = $iFrame.contents().find('[id=btn-refund]')
//             cy.wrap(capture).click({force: true})
//             cy.get('#popup_ok').click()
//             cy.get('#popup_ok').click()
//         })
//         cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'refunded')
        
//     }
 
        //for 1770
        cy.get('#subtab-AdminParentOrders > :nth-child(1)').click()
        cy.get('#subtab-AdminOrders > .link').click()
        cy.get(':nth-child(1) > .column-reference').click()

    }
 }

    


export default Order
