import urllib2
import json

sendHeaders = {'Auth' : 'Bearer YOUR-ID YOUR-TOKEN'}
request = urllib2.Request('http://api.wheresitup.com/v1/sources', None, sendHeaders)

response = urllib2.urlopen(request)
data = response.read()
j = json.loads(data)
sources = j['sources']
for source in sources:
    print source['name'] + ' is in ' + source['location'] + ', ' + source['country']

