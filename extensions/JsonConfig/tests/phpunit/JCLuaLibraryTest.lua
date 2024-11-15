local testframework = require 'Module:TestFramework'

local tests = {
	{ name = 'get with invalid title',
	  func = mw.ext.data.get,
	  args = { ':', 'en' },
	  expect = 'bad argument #1 to "get" (not a valid title)',
	},
	{ name = 'get with invalid language code',
	  func = mw.ext.data.get,
	  args = { 'Special:BlankPage', '"' },
	  expect = 'bad argument #2 to "get" (not a valid language code)',
	},
}

return testframework.getTestProvider( tests )
