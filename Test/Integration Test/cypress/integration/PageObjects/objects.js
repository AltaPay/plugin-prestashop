require('cypress-xpath')

class Order
{
    clrcookies(){
        cy.clearCookies()
    }
    visit()
    {
        cy.fixture('config').then((url)=>{
        cy.visit(url.url) 
        cy.get('.login').click()   
            })    
    }

    signin(){
        cy.fixture('config').then((signin)=>{
        cy.get('#email').type(signin.usrname_cust)
        cy.get('#passwd').type(signin.pass_cust)
        cy.get('#SubmitLogin > span').click()
        cy.wait(1000)
        cy.get('.logo').click()
        cy.wait(1000)
        })
    }
    
    addproduct(){
        cy.get('#homefeatured > li.ajax_block_product.col-xs-12.col-sm-4.col-md-3.first-in-line.first-item-of-tablet-line.first-item-of-mobile-line').click()
        cy.get('.icon-plus').click().click()
        cy.get('.exclusive > span').click()
        cy.get('.button-medium > span').click()
        cy.get('.cart_navigation > .button > span').click()
        cy.get('.cart_navigation > .button > span').click()
        cy.get('label').click()
        cy.get('.cart_navigation > .button > span').click().wait(2000)
        
    }

    cc_payment(){
        cy.get(':nth-child(3) > .col-xs-12 > .payment_module > .altapay').click()
        cy.get('[id=creditCardNumberInput]').type('4111111111111111')
        cy.get('#emonth').type('01')
        cy.get('#eyear').type('2023')
        cy.get('#cvcInput').type('123')
        cy.get('#cardholderNameInput').type('testname')
        cy.get('#pensioCreditCardPaymentSubmitButton').click().wait(2000)
        cy.get('.dark > strong').should('include.text', 'placed on')

    }

    klarna_payment(){

        cy.get(':nth-child(2) > .col-xs-12 > .payment_module > .altapay').click().wait(1000)
        cy.get('[id=submitbutton]').click().wait(3000)
        cy.get('[id=klarna-pay-later-fullscreen]').then(function($iFrame){
            const mobileNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-phone-number]')
            cy.wrap(mobileNum).type('(452) 012-3456')
            const personalNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-national-identification-number]')
            cy.wrap(personalNum).type('1012201234')
            const submit = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-continue-button]')
            cy.wrap(submit).click()
            
        })
        
        cy.wait(1000)
        cy.get('.dark > strong').should('include.text', 'placed on')
        
        
    }

    admin()
    {
            cy.clearCookies()
            cy.fixture('config').then((admin)=>{
            cy.visit(admin.url_admin)
            cy.get('#email').type(admin.usrname_admin)
            cy.get('#passwd').type(admin.pass_admin)
            cy.get('.ladda-label').click()
            cy.visit('http://52.48.95.216/prestashop/prestashop/admin6455nw9r3').wait(3000)
            })

    }

    capture(){

        cy.get('#maintab-AdminParentOrders > .title').click()
        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        //Capture
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-capture]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()
            
            
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'captured')
        
        //Refund
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get(':nth-child(2) > :nth-child(10) > .form-control').type('3').click()
            
        })
            
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'refunded')
        
    }


    

}

export default Order