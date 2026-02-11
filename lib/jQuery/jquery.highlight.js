/*
 * jQuery Highlight plugin
 *
 * Based on highlight v3 by Johann Burkard
 * http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html
 *
 * Modified to support INPUT values.
 */

(function (factory) {
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define(['jquery'], factory);
  } else if (typeof exports === 'object') {
    // Node/CommonJS
    factory(require('jquery'));
  } else {
    // Browser globals
    factory(jQuery);
  }
})(function (jQuery) {
  jQuery.extend({
    highlight: function (
      node,
      re,
      nodeName,
      className,
      callback,
      ignoreDiacritics
    ) {
      if (node.nodeType === 3) {
        // --- TEXT NODE LOGIC ---
        var subject = ignoreDiacritics
          ? jQuery.removeDiacritcs(node.data)
          : node.data;
        var match = subject.match(re);
        if (match) {
          // The new highlight Element Node
          var highlight = document.createElement(nodeName || 'span');
          highlight.className = className || 'highlight';
          // Note that we use the captured value to find the real index
          // of the match. This is because we do not want to include the matching word boundaries
          var capturePos = node.data.indexOf(match[1], match.index);

          // Split the node and replace the matching wordnode
          // with the highlighted node
          var wordNode = node.splitText(capturePos);
          wordNode.splitText(match[1].length);

          var wordClone = wordNode.cloneNode(true);
          highlight.appendChild(wordClone);
          wordNode.parentNode.replaceChild(highlight, wordNode);
          if (typeof callback == 'function') {
            callback(highlight);
          }
          return 1; //skip added node in parent
        }
      } else if (
        node.nodeType === 1 &&
        !/(script|style)/i.test(node.tagName) && // ignore script and style nodes
        !(
          node.tagName === nodeName.toUpperCase() &&
          node.className === className
        )
      ) {
        // --- ELEMENT NODE LOGIC ---

        // 1. Check if this is an INPUT element
        if (node.tagName === 'INPUT') {
          var type = (node.type || 'text').toLowerCase();
          // Filter for text-like inputs
          if (/^(text|search|tel|url|email|password|number)$/.test(type)) {
            var val = ignoreDiacritics
              ? jQuery.removeDiacritcs(node.value)
              : node.value;

            // If the value matches the regex, add the class to the input itself
            if (re.test(val)) {
              jQuery(node).addClass(className);
            }
          }
        }

        // 2. Recurse through children (if any)
        if (node.childNodes && node.childNodes.length) {
          for (var i = 0; i < node.childNodes.length; i++) {
            i += jQuery.highlight(
              node.childNodes[i],
              re,
              nodeName,
              className,
              callback,
              ignoreDiacritics
            );
          }
        }
      }
      return 0;
    },

    removeDiacritcs: function (word) {
      return word
        .replace(/[\u00c0-\u00c6]/g, 'A')
        .replace(/[\u00e0-\u00e6]/g, 'a')
        .replace(/[\u00c7]/g, 'C')
        .replace(/[\u00e7]/g, 'c')
        .replace(/[\u00c8-\u00cb]/g, 'E')
        .replace(/[\u00e8-\u00eb]/g, 'e')
        .replace(/[\u00cc-\u00cf]/g, 'I')
        .replace(/[\u00ec-\u00ef]/g, 'i')
        .replace(/[\u00d1|\u0147]/g, 'N')
        .replace(/[\u00f1|\u0148]/g, 'n')
        .replace(/[\u00d2-\u00d8|\u0150]/g, 'O')
        .replace(/[\u00f2-\u00f8|\u0151]/g, 'o')
        .replace(/[\u0160]/g, 'S')
        .replace(/[\u0161]/g, 's')
        .replace(/[\u00d9-\u00dc]/g, 'U')
        .replace(/[\u00f9-\u00fc]/g, 'u')
        .replace(/[\u00dd]/g, 'Y')
        .replace(/[\u00fd]/g, 'y');
    },

    // https://github.com/knownasilya/jquery-highlight/issues/13
    normalize: function (node) {
      if (!node) {
        return;
      }
      if (node.nodeType == 3) {
        while (node.nextSibling && node.nextSibling.nodeType == 3) {
          node.nodeValue += node.nextSibling.nodeValue;
          node.parentNode.removeChild(node.nextSibling);
        }
      } else {
        jQuery.normalize(node.firstChild);
      }
      jQuery.normalize(node.nextSibling);
    }
  });

  jQuery.fn.unhighlight = function (options) {
    var settings = {
      className: 'highlight',
      element: 'span'
    };

    jQuery.extend(settings, options);

    // NEW: Remove class from inputs that were highlighted
    this.find('input.' + settings.className).removeClass(settings.className);

    // Original: Remove wrapper elements
    return this.find(settings.element + '.' + settings.className)
      .each(function () {
        var parent = this.parentNode;
        parent.replaceChild(this.firstChild, this);
        jQuery.normalize(parent);
      })
      .end();
  };

  jQuery.fn.highlight = function (words, options, callback) {
    var settings = {
      className: 'highlight',
      element: 'span',
      caseSensitive: false,
      wordsOnly: false,
      wordsBoundary: '\\b',
      ignoreDiacritics: false
    };

    jQuery.extend(settings, options);

    if (typeof words === 'string') {
      words = [words];
    }
    words = jQuery.grep(words, function (word) {
      return word != '';
    });
    words = jQuery.map(words, function (word) {
      if (settings.ignoreDiacritics) {
        word = jQuery.removeDiacritcs(word);
      }
      return word.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
    });

    if (words.length === 0) {
      return this;
    }

    var flag = settings.caseSensitive ? '' : 'i';
    // The capture parenthesis will make sure we can match
    // only the matching word
    var pattern = '(' + words.join('|') + ')';
    if (settings.wordsOnly) {
      pattern =
        (settings.wordsBoundaryStart || settings.wordsBoundary) +
        pattern +
        (settings.wordsBoundaryEnd || settings.wordsBoundary);
    }
    var re = new RegExp(pattern, flag);

    return this.each(function () {
      jQuery.highlight(
        this,
        re,
        settings.element,
        settings.className,
        callback,
        settings.ignoreDiacritics
      );
    });
  };
});