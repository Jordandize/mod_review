// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* global STROKEWEIGHT, ANNOTATIONCOLOUR */

/**
 * Provides an in browser PDF editor.
 *
 * @module moodle-reviewfeedback_editpdf-editor
 */

/**
 * Class representing a oval.
 *
 * @namespace M.reviewfeedback_editpdf
 * @class annotationoval
 * @extends M.reviewfeedback_editpdf.annotation
 */
var ANNOTATIONOVAL = function(config) {
    ANNOTATIONOVAL.superclass.constructor.apply(this, [config]);
};

ANNOTATIONOVAL.NAME = "annotationoval";
ANNOTATIONOVAL.ATTRS = {};

Y.extend(ANNOTATIONOVAL, M.reviewfeedback_editpdf.annotation, {
    /**
     * Draw a oval annotation
     * @protected
     * @method draw
     * @return M.reviewfeedback_editpdf.drawable
     */
    draw: function() {
        var drawable,
            bounds,
            shape;

        drawable = new M.reviewfeedback_editpdf.drawable(this.editor);

        bounds = new M.reviewfeedback_editpdf.rect();
        bounds.bound([new M.reviewfeedback_editpdf.point(this.x, this.y),
                      new M.reviewfeedback_editpdf.point(this.endx, this.endy)]);

        shape = this.editor.graphic.addShape({
            type: Y.Ellipse,
            width: bounds.width,
            height: bounds.height,
            stroke: {
               weight: STROKEWEIGHT,
               color: ANNOTATIONCOLOUR[this.colour]
            },
            x: bounds.x,
            y: bounds.y
        });
        drawable.shapes.push(shape);
        this.drawable = drawable;

        return ANNOTATIONOVAL.superclass.draw.apply(this);
    },

    /**
     * Draw the in progress edit.
     *
     * @public
     * @method draw_current_edit
     * @param M.reviewfeedback_editpdf.edit edit
     */
    draw_current_edit: function(edit) {
        var drawable = new M.reviewfeedback_editpdf.drawable(this.editor),
            shape,
            bounds;

        bounds = new M.reviewfeedback_editpdf.rect();
        bounds.bound([new M.reviewfeedback_editpdf.point(edit.start.x, edit.start.y),
                      new M.reviewfeedback_editpdf.point(edit.end.x, edit.end.y)]);

        // Set min. width and height of oval.
        if (!bounds.has_min_width()) {
            bounds.set_min_width();
        }
        if (!bounds.has_min_height()) {
            bounds.set_min_height();
        }

        shape = this.editor.graphic.addShape({
            type: Y.Ellipse,
            width: bounds.width,
            height: bounds.height,
            stroke: {
               weight: STROKEWEIGHT,
               color: ANNOTATIONCOLOUR[edit.annotationcolour]
            },
            x: bounds.x,
            y: bounds.y
        });

        drawable.shapes.push(shape);

        return drawable;
    }
});

M.reviewfeedback_editpdf = M.reviewfeedback_editpdf || {};
M.reviewfeedback_editpdf.annotationoval = ANNOTATIONOVAL;
