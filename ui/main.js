//
// The app to read and manage news aggregator subscriptions/feeds/articles
//
function ciniki_newsaggregator_main() {
    this.init = function() {
        //
        // The panel to list the images by album
        //
        this.menu = new M.panel('News',
            'ciniki_newsaggregator_main', 'menu',
            'mc', 'medium', 'sectioned', 'ciniki.newsaggregator.main.menu');
        this.menu.data = {};
        this.menu.sections = {
            'unread':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':[''],
                'noData':'No subscriptions',
                },
            'categories':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':[''],
                },
            };
        this.menu.noData = function(s) {
            if( this.sections[s].noData != null ) {
                return this.sections[s].noData;
            }
            return null;
        };
        this.menu.sectionData = function(s) {
            return this.data[s];
        };
        this.menu.cellValue = function(s, i, j, d) {
            switch(j) {
                case 0: if( d.category.unread_count > 0 ) { 
                            return '' + d.category.name + ' <span class="count">' + d.category.unread_count + '</span>';
                        } 
                        return d.category.name;
            }
        };
        this.menu.rowFn = function(s, i, d) {
            switch (s) {
                case 'unread':return 'M.ciniki_newsaggregator_main.showAllArticles(\'M.ciniki_newsaggregator_main.showMenu();\');';
                case 'categories':return 'M.ciniki_newsaggregator_main.showFeeds(\'M.ciniki_newsaggregator_main.showMenu();\',\'' + encodeURIComponent(d.category.name) + '\');';
            }
        };
        this.menu.addButton('add', 'Add', 'M.ciniki_newsaggregator_main.showEdit(\'M.ciniki_newsaggregator_main.showMenu();\',0,\'\');');
        this.menu.addClose('Back');

        //
        // The panel to show a category of subscriptions
        //
        this.feeds = new M.panel('News',
            'ciniki_newsaggregator_main', 'feeds',
            'mc', 'medium', 'sectioned', 'ciniki.newsaggregator.main.feeds');
        this.feeds.category = null;
        this.feeds.sections = {
            'all':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':[''],
                },
            'feeds':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':[''],
                'noData':'No subscriptions',
                'addTxt':'Add Subscription',
                'addFn':'M.ciniki_newsaggregator_main.showEdit(\'M.ciniki_newsaggregator_main.showFeeds();\',0,M.ciniki_newsaggregator_main.feeds.category);',
                },
            };
        this.feeds.noData = function(s) {
            if( this.sections[s].noData != null ) {
                return this.sections[s].noData;
            }
            return null;
        };
        this.feeds.sectionData = function(s) {
            return this.data[s];
        };
        this.feeds.cellValue = function(s, i, j, d) {
            switch(j) {
                case 0: if( d.feed.unread_count > 0 ) { 
                            return '' + d.feed.title + ' <span class="count">' + d.feed.unread_count + '</span>';
                        } 
                        return d.feed.title;
            }
        };
        this.feeds.rowFn = function(s, i, d) {
            switch (s) {
                case 'all':return 'M.ciniki_newsaggregator_main.showArticles(\'M.ciniki_newsaggregator_main.showFeeds();\',M.ciniki_newsaggregator_main.feeds.category,null);';
                case 'feeds':return 'M.ciniki_newsaggregator_main.showFeed(\'M.ciniki_newsaggregator_main.showFeeds();\',\'' + encodeURIComponent(d.feed.id) + '\');';
            }
        };
        this.feeds.addButton('add', 'Add', 'M.ciniki_newsaggregator_main.showEdit(\'M.ciniki_newsaggregator_main.showFeeds();\',0,M.ciniki_newsaggregator_main.feeds.category);');
        this.feeds.addClose('Back');

        //
        // The panel to display the list of articles
        //
        this.articles = new M.panel('News',
            'ciniki_newsaggregator_main', 'articles',
            'mc', 'large', 'sectioned', 'ciniki.newsaggregator.main.articles');
        this.articles.category = null;
        this.articles.feed_id = null;
        this.articles.sections = {
            'articles':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':['multiline','',''],
                'noData':'No articles found',
                },
            };
        this.articles.sectionData = function(s) {
            return this.data;
        };
        this.articles.noData = function(s) {
            return this.sections[s].noData;
        };
        this.articles.cellValue = function(s, i, j, d) {
            var title = '';
            if( d.article.read == 'yes' ) {
                title = d.article.title;
            } else {
                title = '<b>' + d.article.title + '</b>';
            }
            var feed_title = '';
            if( this.feed_id == null && d.article.feed_title != '' ) {
                feed_title = '[' + d.article.feed_title + '] ';
            }
            return '<span class="maintext">' + title + '</span>'
                + '<span class="subtext">' + feed_title + d.article.published_date + '</span>';
        };
        this.articles.rowFn = function(s, i, d) {
            return 'M.ciniki_newsaggregator_main.showArticle(\'M.ciniki_newsaggregator_main.showArticles();\',\'' + d.article.id + '\',M.ciniki_newsaggregator_main.articles.data);';
        };
        this.articles.addClose('Back');

        //
        // The panel to display the feed and it's unread/read articles
        //
        this.feed = new M.panel('Feed',
            'ciniki_newsaggregator_main', 'feed',
            'mc', 'large', 'sectioned', 'ciniki.newsaggregator.main.feed');
        this.feed.feed_id = null;
        this.feed.sections = {
            'feed':{'label':'', 'list':{
                'title':{'label':'Title', 'value':''},
                'site_url':{'label':'Site', 'value':''},
                'last_checked':{'label':'Updated', 'value':''},
                }},
            '_buttons':{'label':'', 'buttons':{
                'unsubscribe':{'label':'Unsubscribe', 'fn':'M.ciniki_newsaggregator_main.unsubFeed();'},
                }},
            'unread':{'label':'Unread', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':['multiline'],
                'noData':'No unread articles',
                },
            'read':{'label':'Read', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':['multiline'],
                'noData':'No read articles',
                },
            };
        this.feed.sectionData = function(s) {
            if( s == 'feed' ) { return this.sections[s].list; }
            return this.data[s];
        };
        this.feed.cellValue = function(s, i, j, d) {
            var title = '';
            if( d.article.read == 'yes' ) {
                title = d.article.title;
            } else {
                title = '<b>' + d.article.title + '</b>';
            }
            return '<span class="maintext">' + title + '</span>'
                + '<span class="subtext">' + d.article.published_date + '</span>';
        };
        this.feed.listLabel = function(s, i, d) {
            return this.sections[s].list[i].label;
        };
        this.feed.listValue = function(s, i, d) {
            if( i == 'site_url' ) {
                return '<a target="_blank" href="' + this.data[s][i] + '">' + this.data[s][i] + '</a>';
            }
            return this.data[s][i];
        };
        this.feed.rowFn = function(s, i, d) {
            return 'M.ciniki_newsaggregator_main.showArticle(\'M.ciniki_newsaggregator_main.showFeed();\',\'' + d.article.id + '\',M.ciniki_newsaggregator_main.feed.data[\'' + s + '\']);';
        };
        this.feed.addClose('Back');

        //
        // The panel to display an article
        //
        this.article = new M.panel('Article',
            'ciniki_newsaggregator_main', 'article',
            'mc', 'large', 'sectioned', 'ciniki.newsaggregator.main.article');
        this.article.prev_article_id = 0;
        this.article.next_article_id = 0;
        this.article.sections = {
            'details':{'label':'', 'type':'simplegrid', 'num_cols':1,
                'headerValues':null,
                'cellClasses':['multiline'],
                },
//          'details':{'label':'', 'list':{
//              'title':{'label':'Title', 'value':''},
//              'published_date':{'label':'Published', 'value':''},
//              'url':{'label':'URL', 'value':''},
//              }},
            'content':{'label':'', 'type':'htmlcontent'},
            };
        this.article.sectionData = function(s) {
            if( s == 'details' ) { 
                return {'title':this.data.title,
                    'published_date':this.data.published_date,
//                  'url':this.data.url,
                    };
//              return this.sections.details.list;
            }

            return this.data[s];
        };
//      this.article.listLabel = function(s, i, d) {
//          return this.sections[s].list[i].label;
//      };
        this.article.cellValue = function(s, i, d) {
            if( i == 'title' ) {
                return '<span class="maintext">' + this.data.title + '</span><span class="subtext">' + this.data.feed_title + '</span>';
            }
            if( i == 'published_date' ) {
                return '<span class="maintext">' + this.data.published_date + '</span><span class="subtext"><a target="_blank" href="' + this.data.url + '">' + this.data.url + '</a></span>';
            }
            if( i == 'url' ) {
                return '<a target="_blank" href="' + this.data[i] + '">' + this.data[i] + '</a>';
            }
            return this.data[i];
        };
//      this.article.cellFn = function(s, i, j, d) {
//          if( i == 'published_date' ) {
//              return 'window.open("' + this.data.url + '","_blank");';
//          }
//      };
        this.article.prevButtonFn = function() {
            if( this.prev_article_id > 0 ) {
                return 'M.ciniki_newsaggregator_main.showArticle(null,\'' + this.prev_article_id + '\');';
            }
            return null;
        };
        this.article.nextButtonFn = function() {
            if( this.next_article_id > 0 ) {
                return 'M.ciniki_newsaggregator_main.showArticle(null,\'' + this.next_article_id + '\');';
            }
            return null;
        };
        this.article.addButton('next', 'Next');
        this.article.addClose('Back');
        this.article.addLeftButton('prev', 'Prev');
        this.article.onloadCb = function(rsp) {
            M.ciniki_newsaggregator_main.article.data = rsp.article;
            // Setup next/prev buttons
            M.ciniki_newsaggregator_main.article.prev_article_id = 0;
            M.ciniki_newsaggregator_main.article.next_article_id = 0;
            if( M.ciniki_newsaggregator_main.article.list != null ) {
                for(i in M.ciniki_newsaggregator_main.article.list) {
                    if( M.ciniki_newsaggregator_main.article.next_article_id == -1 ) {
                        M.ciniki_newsaggregator_main.article.next_article_id = M.ciniki_newsaggregator_main.article.list[i].article.id;
                        break;
                    } else if( M.ciniki_newsaggregator_main.article.list[i].article.id == M.ciniki_newsaggregator_main.article.article_id ) {
                        // Flag to pickup next article
                        M.ciniki_newsaggregator_main.article.next_article_id = -1;
                    } else {
                        M.ciniki_newsaggregator_main.article.prev_article_id = M.ciniki_newsaggregator_main.article.list[i].article.id;
                    }
                }
            }

            M.ciniki_newsaggregator_main.article.refresh();
            M.ciniki_newsaggregator_main.article.show();
            // Update the links within the article to open in new window
            var c = M.gE(M.ciniki_newsaggregator_main.article.panelUID + '_content');
            var links = c.getElementsByTagName('a');
            var len = links.length;
            for(var i=0;i<len;i++) {
                links[i].target = "_blank";
            }
        };

        //
        // The panel to edit an existing subscription
        //
        this.edit = new M.panel('Edit Subscription',
            'ciniki_newsaggregator_main', 'edit',
            'mc', 'medium', 'sectioned', 'ciniki.newsaggregator.main.edit');
        this.edit.data = null;
        this.edit.sections = {
            'details':{'label':'Subscription', 'fields':{
                'feed_url':{'label':'URL', 'type':'text'},
                'category':{'label':'Category', 'type':'text'},
                }},
            '_buttons':{'label':'', 'buttons':{
                '_save':{'label':'Save', 'fn':'M.ciniki_newsaggregator_main.saveSubscription();'},
                }},
            };
        this.edit.fieldValue = function(s, i, d) { return this.data[i]; }
        this.edit.addClose('Cancel');

    };

    this.start = function(cb, appPrefix, aG) {
        args = {};
        if( aG != null ) {
            args = eval(aG);
        }

        //
        // Create container
        //
        var appContainer = M.createContainer(appPrefix, 'ciniki_newsaggregator_main', 'yes');
        if( appContainer == null ) {
            M.alert('App Error');
            return false;
        }
    
        this.showMenu(cb);
        return true;
    };

    this.showMenu = function(cb) {
        var rsp = M.api.getJSONCb('ciniki.newsaggregator.subscriptionListCategories', 
            {'tnid':M.curTenantID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( rsp.feeds != null ) {
                    // If only feeds returned, then no categories specified
                    var p = M.ciniki_newsaggregator_main.feeds;
                    if( rsp.category != null ) {
                        p.category = rsp.category;
                    }
                    M.ciniki_newsaggregator_main.setFeedsData(rsp);
                    p.refresh();
                    p.show(cb);
                } else {
                    var p = M.ciniki_newsaggregator_main.menu;
                    p.data = {
                        'unread':[{'category':{'name':'All', 'unread_count':rsp.unread_count}}],
                        };
                    if( rsp.categories != null && rsp.categories.length > 0 ) {
                        p.data.categories = rsp.categories;
                        p.sections.categories.visible = 'yes';
                    } else {
                        p.sections.categories.visible = 'no';
                    }
                    p.refresh();
                    p.show(cb);
                }
            });
    };

    this.showFeeds = function(cb, category) {
        if( category != null ) {
            this.feeds.category = category;
        }
        var args = {};
        if( this.feeds.category != null ) {
            args = {'tnid':M.curTenantID, 'category':this.feeds.category};
        } else {
            args = {'tnid':M.curTenantID};
        }
        var rsp = M.api.getJSONCb('ciniki.newsaggregator.subscriptionList', args, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            M.ciniki_newsaggregator_main.setFeedsData(rsp);
            M.ciniki_newsaggregator_main.feeds.refresh();
            M.ciniki_newsaggregator_main.feeds.show(cb);
            });
    };

    this.setFeedsData = function(rsp) {
        this.feeds.data = {
            'all':[{'feed':{'title':'All', 'category':this.feeds.category, 'unread_count':rsp.unread_count}}],
            };
        if( rsp.feeds != null && rsp.feeds.length > 0 ) {
            this.feeds.data.feeds = rsp.feeds;
            this.feeds.sections.all.visible = 'yes';
        } else {
            this.feeds.sections.all.visible = 'no';
        }
    };

    this.showArticles = function(cb, category, feed) {
        if( category != null ) {    
            this.articles.category = category;
            this.articles.feed_id = null;
        } 
        if( feed != null ) {
            this.articles.feed_id = feed;
            this.articles.category = null;
        }
        var args = {};
        if( this.articles.category != null ) {
            args = {'tnid':M.curTenantID, 'category':this.articles.category};
        } else if( this.articles.feed_id != null ) {
            args = {'tnid':M.curTenantID, 'feed_id':this.articles.feed_id};
        } else {
            args = {'tnid':M.curTenantID};
        }
        var rsp = M.api.getJSONCb('ciniki.newsaggregator.articleList', args, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_newsaggregator_main.articles;
            p.data = rsp.articles;
            p.refresh();
            p.show(cb);
            });
    };

    this.showFeed = function(cb, fid) {
        if( fid != null ) { 
            this.feed.feed_id = fid;
        }
        // Load articles
        var rsp = M.api.getJSONCb('ciniki.newsaggregator.feedArticleList', 
            {'tnid':M.curTenantID, 'feed_id':this.feed.feed_id}, function(rsp) {
                M.ciniki_newsaggregator_main.feed.data = rsp;
                if( rsp.unread != null && rsp.unread.length > 0 ) {
                    M.ciniki_newsaggregator_main.feed.sections.unread.visible = 'yes';
                } else {
                    M.ciniki_newsaggregator_main.feed.sections.unread.visible = 'no';
                }
                if( rsp.read != null && rsp.read.length > 0 ) {
                    M.ciniki_newsaggregator_main.feed.sections.read.visible = 'yes';
                } else {
                    M.ciniki_newsaggregator_main.feed.sections.read.visible = 'no';
                }

                M.ciniki_newsaggregator_main.feed.refresh();
                M.ciniki_newsaggregator_main.feed.show(cb);
            });
        if( rsp.stat != 'ok' ) {
            M.api.err(rsp);
            return false;
        }
    };

    this.showAllArticles = function(cb) {
        this.articles.category = null;
        this.articles.feed_id = null;
        this.showArticles(cb);
    };

    this.showArticle = function(cb, aid, list) {
        if( aid != null ) { 
            this.article.article_id = aid;
        }
        if( list != null ) {
            this.article.list = list;
        }
        if( cb != null ) {
            M.ciniki_newsaggregator_main.article.cb = cb;
        }
        var rsp = M.api.getJSONCb('ciniki.newsaggregator.articleGet', 
            {'tnid':M.curTenantID, 'article_id':this.article.article_id}, 
            M.ciniki_newsaggregator_main.article.onloadCb);
    };


    this.showEdit = function(cb, sid, category) {
        if( sid != null ) {
            this.edit.subscription_id = sid;
        }
        if( this.edit.subscription_id > 0 ) {
            var rsp = M.api.getJSONCb('ciniki.newsaggregator.subscriptionGet', 
                {'tnid':M.curTenantID, 'subscription_id':this.edit.subscription_id}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    var p = M.ciniki_newsaggregator_main.edit;
                    p.data = rsp.subscription;
                    p.refresh();
                    p.show(cb);
                });
        } else {
            this.edit.reset();
            this.edit.data = {'feed_url':''};
            if( category != null ) {
                this.edit.data.category = unescape(category);
            }
            this.edit.refresh();
            this.edit.show(cb);
        }
    };

    this.saveSubscription = function() {
        if( this.edit.subscription > 0 ) {
            var c = this.edit.serializeFormData('no');
            if( c != '' ) {
                var rsp = M.api.postJSONFormData('ciniki.newsaggregator.subscriptionUpdate', 
                    {'tnid':M.curTenantID, 'subscription_id':this.edit.subscription_id}, c, function(rsp) {
                        if( rsp.stat != 'ok' ) {
                            M.api.err(rsp);
                            return false;
                        }
                        M.ciniki_newsaggregator_main.edit.close();
                    });
            } else {
                M.ciniki_newsaggregator_main.edit.close();
            }
        } else {
            var c = this.edit.serializeForm('yes');
            if( c != null ) {
                var rsp = M.api.postJSONFormData('ciniki.newsaggregator.subscriptionAdd', {'tnid':M.curTenantID}, c,
                    function(rsp) {
                        if( rsp.stat != 'ok' ) {
                            M.api.err(rsp);
                            return false;
                        } else {
                            M.ciniki_newsaggregator_main.edit.close();
                        }
                    });
            }
        }
    };

    this.unsubFeed = function() {
        M.confirm("Are you sure you want to unsubscribe from this feed?",null,function() {
            M.api.getJSONCb('ciniki.newsaggregator.subscriptionDelete', 
                {'tnid':M.curTenantID, 'feed_id':M.ciniki_newsaggregator_main.feed.feed_id}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    } 
                    M.ciniki_newsaggregator_main.feed.close();
                });
        }
    };
}
