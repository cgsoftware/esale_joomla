##############################################################################
#
# Copyright (c) 2004 TINY SPRL. (http://tiny.be) All Rights Reserved.
#
# $Id: sale.py 1005 2005-07-25 08:41:42Z nicoe $
#
# WARNING: This program as such is intended to be used by professional
# programmers who take the whole responsability of assessing all potential
# consequences resulting from its eventual inadequacies and bugs
# End users who are looking for a ready-to-use solution with commercial
# garantees and support are strongly adviced to contract a Free Software
# Service Company
#
# This program is Free Software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#
##############################################################################

from osv import fields, osv

import time
import xmlrpclib
from tools import config

import urllib2

class product_product(osv.osv):
    _inherit = "product.product"
    _columns = {
        'exportable' : fields.boolean('Exportable'), 
        'contrib' : fields.float('contrib', digits=(16, int(config['price_accuracy'])), help=""), 
        'picture':    	fields.binary('Image'),
        'article':    	fields.char('Article', size=32,  required=False,  select=True), 
    }
    _order = "default_code"
product_product()

class product_template(osv.osv):
    _inherit = "product.template"
    _columns = {
        'product_special' : fields.boolean('Product Special'),         
        'product_lenght' : fields.float('contrib', digits=(8, 4)),
        'product_width' : fields.float('contrib', digits=(8, 4)),
        'product_height' : fields.float('contrib', digits=(8, 4)),
        'weight_uom': fields.selection([('Kg', 'Kg'), ('g', 'g'), ('q', 'q')], 'Weight UOM', required=False),
        'lenght_uom': fields.selection([('m', 'm'), ('cm', 'cm'), ('km', 'km')], 'Lenght UOM', required=False),
        'slideshow':fields.char('SlideShow', size=64,  required=False,  help="Campo per selezionare la galleria da visualizzare. "), 
    }
product_template()

class esale_manufacturer(osv.osv):
    _columns = {
        'manufacturer_id' : fields.integer('Manufacturer ID', readonly=True, required=True),
        'mf_name' : fields.char('MF Name', size=64, required=True),
        'mf_email' : fields.char('MF Email', size=255, required=True),
        'mf_desc' : fields.text('MF Description',translate=False),
        'mf_category_id' :  fields.integer('MF Category ID', readonly=True, required=True),
        'mf_url' : fields.char('MF URL', size=255, required=True),
    }
#esale_manufacturer() Forse non serve. Usare il modulo electronic_product



class esale_joomla_web(osv.osv):
    _name = "esale_joomla.web"
    _description = "eCommerce Website"

    _columns = {
        'name': fields.char('Name', size=64, required=True),
        'url': fields.char('URL', size=64, required=True),
        'shop_id': fields.many2one('sale.shop', 'Sale Shop', required=True),
        'active': fields.boolean('Active'),
        'product_ids': fields.one2many('esale_joomla.product', 'web_id', string='Products'),
        'tax_ids': fields.one2many('esale_joomla.tax', 'web_id', string='Taxes'),
        'taxes_included_ids': fields.many2many('account.tax', 'esale_joomla_web_taxes_included_rel', 'esale_joomla_web_id', 'tax_id',
                                               'Taxes included', domain=[('parent_id', '=', False)]),
        'category_ids': fields.one2many('esale_joomla.category', 'web_id', string='Categories'),
        'language_id': fields.many2one('res.lang', 'Language'),
    }

    _defaults = {
        'active': lambda *a: 1
    }

    def tax_import(self, cr, uid, ids, *args):
        for website in self.browse(cr, uid, ids):
            server = xmlrpclib.ServerProxy("%s/tinyerp-synchro.php" % website.url)
            taxes = server.get_taxes()
            for tax in taxes:
                value = {
                    'web_id': website.id,
                    'esale_joomla_id': tax[0],
                    'name': tax[1]
                }
                self.pool.get('esale_joomla.tax').create(cr, uid, value)
        return True

    def lang_import(self, cr, uid, ids, *args):
        for website in self.browse(cr, uid, ids):
            server = xmlrpclib.ServerProxy("%s/tinyerp-synchro.php" % website.url)
            languages = server.get_languages()
            for language in languages:
                value = {
                    'web_id': website.id,
                    'esale_joomla_id': language[0],
                    'name': language[1]
                }
                self.pool.get('esale_joomla.lang').create(cr, uid, value)
        return True

    def category_import(self, cr, uid, ids, *args):
        def _decode(name, idn):
            """DB is corrupted with utf8 and latin1 chars."""
            decoded_name = name
            if isinstance(name, unicode):
                try:
                    decoded_name = name.encode('utf8')
                except:
                    decoded_name = name
            else:
                try:
                    decoded_name = unicode(name, 'utf8')
                except:
                    try:
                        decoded_name = unicode(name, 'latin1').encode('utf8')
                    except:
                        decoded_name = name

            return decoded_name

        for website in self.browse(cr, uid, ids):
            server = xmlrpclib.ServerProxy("%s/tinyerp-synchro.php" % website.url)
            categories = server.get_categories()
            category_pool = self.pool.get('esale_joomla.category')

            encoding_errors = []

            for category in categories:
                category_name = urllib2.unquote(category[1].replace('+', '%20'))
                category_name = _decode(category_name, category[0])

                value = {
                    'web_id': website.id,
                    'esale_joomla_id': category[0],
                    u'name': len(category_name) > 64 and category_name[0:61] + '...' or category_name
                }
                existing = category_pool.search(cr, uid, [('web_id', '=', website.id), ('esale_joomla_id', '=', category[0])])
                if len(existing) > 0:
                    try:
                        category_pool.write(cr, uid, existing, value)
                    except Exception:
                        encoding_errors.append(str(value['esale_joomla_id']))
                else:
                    category_pool.create(cr, uid, value)

            if encoding_errors:
                print "These records contains wrong encoding:\n" + ", ".join(encoding_errors)

        return True

esale_joomla_web()


class esale_joomla_tax(osv.osv):
    _name = "esale_joomla.tax"
    _description = "eSale Tax"
    _columns = {
        'name': fields.char('Tax name', size=32, required=True),
        'esale_joomla_id': fields.integer('eSale id'),
        'tax_id': fields.many2one('account.tax', 'Tax'),
        'web_id': fields.many2one('esale_joomla.web', 'Website')
    }

esale_joomla_tax()


class esale_joomla_category(osv.osv):
    _name = "esale_joomla.category"
    _description = "eSale Category"
    _columns = {
        'name': fields.char('Name', size=64, required=True),
        'esale_joomla_id': fields.integer('Web ID', readonly=True, required=True),
        'web_id': fields.many2one('esale_joomla.web', 'Website'),
        'category_id': fields.many2one('product.category', 'Category'),
        'include_childs': fields.boolean('Include Childs', help="If checked, Tiny ERP will also export products from categories that are childs of this one."),
    }

esale_joomla_category()


class esale_joomla_product(osv.osv):
    _name = "esale_joomla.product"
    _description = "eSale Product"
    _columns = {
        'web_id': fields.many2one('esale_joomla.web', 'Web Ref'),
        'name': fields.char('Name', size=64, required=True),
        'product_id': fields.many2one('product.product', 'Product', required=True),
        'esale_joomla_id': fields.integer('eSale product id'),
        'esale_joomla_tax_id': fields.many2one('esale_joomla.tax', 'eSale tax'),
    }

    def onchange_product_id(self, cr, uid, ids, product_id, web_id=False):
        value = {}
        if product_id:
            product = self.pool.get('product.product').browse(cr, uid, product_id)
            value['name'] = product.name
        return {'value': value}

esale_joomla_product()


class esale_joomla_language(osv.osv):
    _name = "esale_joomla.lang"
    _description = "eSale Language"
    _columns = {
        'name': fields.char('Name', size=32, required=True),
        'esale_joomla_id': fields.integer('Web ID', required=True),
        'language_id': fields.many2one('res.lang', 'Language'),
        'web_id': fields.many2one('esale_joomla.web', 'Website')
    }

esale_joomla_language()


class esale_joomla_partner(osv.osv):
    _name = 'esale_joomla.partner'
    _description = 'eShop Partner'
    _columns = {
        'name': fields.char('Name', size=64, required=True),
        'esale_id': fields.char('eSale ID', size=64),
        'address': fields.char('Address', size=128),
        'city': fields.char('City', size=64),
        'zip': fields.char('Zip', size=64),
        'country': fields.char('Country', size=64),
        'email': fields.char('Mail', size=64),
        'state': fields.char('State', size=64),
        'address_id': fields.many2one('res.partner.address', 'Partner Address'),
    }

    def address_set(self, cr, uid, ids, context={}):
        for adr in self.browse(cr, uid, ids, context):
            if adr.address_id:
                continue
            country = self.pool.get('res.country').name_search(cr, uid, adr.country)
            state = self.pool.get('res.country.state').name_search(cr, uid, adr.state)
            create_id = self.pool.get('res.partner').create(cr, uid, {
                'name': adr.name,
            })
            address_dico = {
                    'street': adr.address,
                    'partner_id': create_id,
                    'zip': adr.zip,
                    'city': adr.city,
                    'email': adr.email,
            }
            if adr.country and len(country) == 1:
                address_dico['country_id'] = country and country[0][0]
            if adr.state and len(state) == 1:
                address_dico['state_id'] = state and state[0][0]

            create_id2 = self.pool.get('res.partner.address').create(cr, uid, address_dico)

            self.write(cr, uid, [adr.id], {'address_id': create_id2})
        return True

esale_joomla_partner()


class esale_joomla_order(osv.osv):
    _name = 'esale_joomla.order'
    _columns = {
        'name': fields.char('Order Description', size=64, required=True),
        'state': fields.selection([
            ('draft', 'Draft'),
            ('done', 'Done'),
            ('cancel', 'Cancel')
        ], 'Order State'),
        'date_order': fields.date('Date Ordered', required=True),

        'epartner_shipping_id': fields.many2one('esale_joomla.partner', 'Joomla Shipping Address', required=True),
        'epartner_invoice_id': fields.many2one('esale_joomla.partner', 'Joomla Invoice Address', required=True),

        'partner_id': fields.many2one('res.partner', 'Contact Address'),
        'partner_shipping_id': fields.many2one('res.partner.address', 'Shipping Address'),
        'partner_invoice_id': fields.many2one('res.partner.address', 'Invoice Address'),

        'web_id': fields.many2one('esale_joomla.web', 'Web Shop', required=True),
        'web_ref': fields.integer('Web Ref'),

        'order_lines': fields.one2many('esale_joomla.order.line', 'order_id', 'Order Lines'),
        'order_id': fields.many2one('sale.order', 'Sale Order'),
        'note': fields.text('Notes'),
    }

    _defaults = {
        'date_order': lambda *a: time.strftime('%Y-%m-%d'),
        'state': lambda *a: 'draft',
    }

    def order_create(self, cr, uid, ids, context={}):
        for order in self.browse(cr, uid, ids, context):
            if not (order.partner_id and order.partner_invoice_id and order.partner_shipping_id):
                raise osv.except_osv('No addresses !', 'You must assign addresses before creating the order.')
            #pricelist_id=order.partner_id.property_product_pricelist[0]
            pricelist_id = order.partner_id.property_product_pricelist.id
            order_lines = []
            for line in order.order_lines:
                val = {
                    'name': line.name,
                    'product_uom_qty': line.product_qty,
                    'product_id': line.product_id.id,
                    'product_uom': line.product_uom_id.id,
                    'price_unit': line.price_unit,
                }
                fpos = order.partner_id.property_account_position and order.partner_id.property_account_position.id or False
                val_new = self.pool.get('sale.order.line').product_id_change(cr, uid, None, pricelist_id, line.product_id.id, line.product_qty,
                                                                             line.product_uom_id.id, name=line.name, partner_id=order.partner_id.id, fiscal_position=fpos)['value']
                del val_new['price_unit']
                #del val_new['weight']
                del val_new['th_weight']
                val_new['product_uos'] = 'product_uos' in val_new and val_new['product_uos'] and val_new['product_uos'][0] or False
                val.update(val_new)
                val['tax_id'] = 'tax_id' in val and [(6, 0, val['tax_id'])] or False
                order_lines.append((0, 0, val))

            order_id = self.pool.get('sale.order').create(cr, uid, {
                'name': order.name,
                'shop_id': order.web_id.shop_id.id,
                'origin': 'WEB:' + str(order.web_ref),
                'user_id': uid,
                'note': order.note or '',
                'partner_id': order.partner_id.id,
                'partner_invoice_id': order.partner_invoice_id.id,
                'partner_order_id': order.partner_invoice_id.id,
                'partner_shipping_id': order.partner_shipping_id.id,
                'pricelist_id': pricelist_id,
                'order_line': order_lines,
                'fiscal_position': order.partner_id.property_account_position.id
            })
            self.write(cr, uid, [order.id], {'state': 'done', 'order_id': order_id})
#           wf_service = netsvc.LocalService("workflow")
#           wf_service.trg_validate(uid, 'sale.order', order_id, 'order_confirm', cr)
        return True

    def address_set(self, cr, uid, ids, *args):
        done = []
        for order in self.browse(cr, uid, ids):
            for idn in [order.epartner_shipping_id.id, order.epartner_invoice_id.id]:
                if idn not in done:
                    done.append(idn)
                    self.pool.get('esale_joomla.partner').address_set(cr, uid, [idn])
            self.write(cr, uid, [order.id], {
                'partner_shipping_id': order.epartner_invoice_id.address_id.id,
                'partner_id': order.epartner_invoice_id.address_id.partner_id.id,
                'partner_invoice_id': order.epartner_shipping_id.address_id.id,

            })
        return True

    def order_cancel(self, cr, uid, ids, context={}):
        self.write(cr, uid, ids, {'state': 'cancel'})
        return True

esale_joomla_order()


class esale_joomla_order_line(osv.osv):
    _name = 'esale_joomla.order.line'
    _description = 'eSale Order line'
    _columns = {
        'name': fields.char('Order Line', size=64, required=True),
        'order_id': fields.many2one('esale_joomla.order', 'eOrder Ref'),
        'product_qty': fields.float('Quantity', digits=(16, 2), required=True),
        'product_id': fields.many2one('product.product', 'Product', domain=[('sale_ok', '=', True)], change_default=True),
        'product_uom_id': fields.many2one('product.uom', 'Unit of Measure', required=True),
        'price_unit': fields.float('Unit Price', digits=(16, int(config['price_accuracy'])), required=True),
    }
    _defaults = {
    }

esale_joomla_order_line()


class esale_joomla_web_exportlog(osv.osv):
    _name = 'esale_joomla.web.exportlog'
    _description = "eSale webshop Synchronisation log"
    _columns = {
        'name': fields.char('Synchronisation Log', size=64, required=True),
        'web_id': fields.many2one('esale_joomla.web', 'Web Ref'),
        'log_date': fields.datetime('Log date', required=True),
        'log_type': fields.selection([('product', 'Product'), ('category', 'Category'), ('lang', 'Language'), ('tax', 'Tax')], 'Export type', readonly=True),
        'user_id': fields.many2one('res.users', 'Exported By', required=True),
    }
    _defaults = {
        'log_date': lambda *a: time.strftime('%Y-%m-%d %H:%M:%S'),
        'user_id': lambda obj, cr, uid, context: uid,
    }

esale_joomla_web_exportlog()

